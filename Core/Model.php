<?php
namespace Ideal\Core;

use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Field\Url;
use Ideal\Core\Pagination;


abstract class Model
{
    public $params;
    public $fields;
    protected $_table;
    protected $prevStructure;
    protected $path = array();
    protected $parentUrl;
    protected $module;
    protected $prevModel;

    public function __construct($prevStructure)
    {
        $this->prevStructure = $prevStructure;

        $config = Config::getInstance();

        $parts = preg_split('/[_\\\\]+/', get_class($this));
        $this->module = $parts[0];
        $module = ($this->module == 'Ideal') ? '' : $this->module . '/';
        $type = $parts[1]; // Structure или Template
        $structureName = $parts[2];
        $structureFullName = $this->module . '_' . $structureName;

        if ($structureName == 'Home') {
            $type = 'Home';
        }

        switch($type):
            case 'Home':
                // Находим начальную структуру
                $structures = $config->structures;
                $structure = reset($structures);
                $type = $parts[1];
                $structureName = $structure['structure'];
                $structureName = substr($structureName, strpos($structureName, '_') + 1);
                break;
            case 'Structure':
                $structure = $config->getStructureByName($structureFullName);
                break;
            case 'Template':
                $includeFile = $module . 'Template/' . $structureName . '/config.php';
                $structure = include($includeFile);
                if (!is_array($structure)) {
                    throw new \Exception('Не удалось подключить файл: ' . $includeFile);
                }
                break;
        endswitch;

        $this->params = $structure['params'];
        $this->fields = $structure['fields'];

        $this->_table = strtolower($config->db['prefix'] . $this->module . '_' . $type . '_' . $structureName);
    }


    public function setprevStructure($prevStructure)
    {
        $this->prevStructure = $prevStructure;
    }


    public function getPath()
    {
        return $this->path;
    }


    public function setObjectById($id)
    {
        $db = Db::getInstance();

        $_sql = "SELECT * FROM {$this->_table} WHERE ID='{$id}'";
        $object = $db->queryArray($_sql);
        if (isset($object[0]['ID'])) {
            // TODO сделать обработку ошибки, когда по ID ничего не нашлось
            $this->object = $object[0];
        }
    }


    public function setObjectByprevStructure($prevStructure)
    {
        $db = Db::getInstance();

        $_sql = "SELECT * FROM {$this->_table} WHERE prev_structure='{$prevStructure}'";
        $object = $db->queryArray($_sql);
        if (isset($object[0]['ID'])) {
            // TODO сделать обработку ошибки, когда по prevStructure ничего не нашлось
            $this->object = $object[0];
        }
    }


    static function getStructureName()
    {
        $parts = explode('_', get_called_class());

        return $parts[2];
    }


    public function setPath($path)
    {
        $this->path = $path;
    }


    public function getParentUrl()
    {
        if ($this->parentUrl != '') return $this->parentUrl;

        $url = new Url\Model();
        $this->parentUrl = $url->setParentUrl($this->path);

        return $this->parentUrl;
    }


    public function getprevStructure()
    {
        return $this->prevStructure;
    }


    /**
     * @param int $page Номер отображаемой страницы
     * @return array Полученный список элементов
     */
    public function getList($page)
    {
        $list = array();
        $where = $this->getWhere("e.prev_structure='{$this->prevStructure}'");
        if ($where === false) return $list;
        $db = Db::getInstance();

        // todo сделать определение elements_site | elements_admin на основании имени класса
        $onPage = $this->params['elements_site'];

        if ($page == 0) $page = 1;
        $start = ($page - 1) * $onPage;

        $_sql = "SELECT e.* FROM {$this->_table} AS e {$where}
                          ORDER BY e.{$this->params['field_sort']} LIMIT {$start}, {$onPage}";
        $list = $db->queryArray($_sql);

        return $list;
    }


    /**
     * Получить общее количество элементов в списке
     * @return array Полученный список элементов
     */
    public function getListCount()
    {
        $db = Db::getInstance();
        $where = $this->getWhere("e.prev_structure='{$this->prevStructure}'");

        // Считываем все элементы первого уровня
        $_sql = "SELECT COUNT(e.ID) FROM {$this->_table} AS e {$where}";
        $list = $db->queryArray($_sql);

        return $list[0]['COUNT(e.ID)'];
    }


    /**
     * Добавление к where-запросу фильтра по category_id
     * @param $where Исходная WHERE-часть
     * @return string Модифицированная WHERE-часть, с расширенным запросом, если установлена GET-переменная category
     */
    protected function getWhere($where)
    {
        if ($where != '') {
            $where = 'WHERE ' . $where;
        }
        return $where;
    }


    /**
     * Получение листалки для шаблона и стрелок вправо/влево
     * @param $pageName Название get-параметра, содержащего страницу
     * @return mixed
     */
    public function getPager($pageName)
    {
        // По заданному названию параметра страницы определяем номер активной страницы
        $request = new Request();
        $page = intval($request->{$pageName});
        $page = ($page == 0) ? 1 : $page;

        // Строка запроса без нашего параметра номера страницы
        $query = $request->getQueryWithout($pageName);

        // todo сделать определение elements_site | elements_admin на основании имени класса
        $onPage = $this->params['elements_site'];

        $countList = $this->getListCount();

        $pagination = new Pagination();
        // Номера и ссылки на доступные страницы
        $pager['pages'] = $pagination->getPages($countList, $onPage, $page, $query, 'page');
        $pager['prev'] = $pagination->getPrev(); // ссылка на предыдущю страницу
        $pager['next'] = $pagination->getNext(); // cсылка на следующую страницу

        return $pager;
    }

    /**
     * Определение пути с помощью prev_structure по инициализированному $this->object
     * @return array Массив, содержащий элементы пути к $this->object
     */
    public function detectPath()
    {
        $config = Config::getInstance();

        $prevStructure = explode('-', $this->object['prev_structure']);
        $sP = array_shift($prevStructure);
        $structure = $config->getStructureById($sP);
        $path = array($structure);
        foreach ($prevStructure as $v) {
            $className = \Ideal\Core\Util::getClassName($structure['structure'], 'Structure') . '\\Site\\Model';
            /* @var $structure \Ideal\Core\Model */
            $structure = new $className($sP);
            $structure->setObjectById($v);
            $elements = $structure->getLocalPath();
            $path = array_merge($path, $elements);
            $structure = end($path);
            $sP .= '-' . $structure['ID'];
        }
        $path = array_merge($path, $this->getLocalPath());
        return $path;
    }

    /**
     * Построение пути в рамках одной структуры.
     * Этот метод обязательно должен быть переопределён перед использованием.
     * Если он не будет переопределён, то будет вызвано исключение.
     * @throws \Exception
     */
    protected function getLocalPath()
    {
        throw new \Exception('Вызов не переопределённого метода getLocalPath');
    }

    public function setPrevModel($prevModel)
    {
        $this->prevModel = $prevModel;
    }

    public function getPrevModel()
    {
        return $this->prevModel;
    }

    public function detectActualModel()
    {
        $config = Config::getInstance();
        $model = $this;
        $count = count($this->path);

        if ($count > 1) {
            $end = $this->path[($count - 1)];
            $prev = $this->path[($count - 2)];
            if ($prev['structure'] != $end['structure']) {
                // Если структура активного элемента не равна структуре предыдущего элемента,
                // то нужно инициализировать модель структуры активного элемента
                $name = explode('\\', get_class($this));
                $modelClassName = Util::getClassName($end['structure'], 'Structure') . '\\' . $name[3] . '\\Model';
                $prevStructure = $config->getStructureByName($prev['structure']);
                /* @var $model Model */
                $model = new $modelClassName($prevStructure['ID'] . '-' . $end['ID']);
                $model->setPath($this->path);
                $model->setPrevModel($this);
            }
        }
        return $model;
    }

    public function __get($name)
    {
        /*
        if ($name == 'object') {
            throw new \Exception('Свойство object упразднено.');
        }
        */
    }
}
