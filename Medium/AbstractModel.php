<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2014 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Medium;

use Ideal\Core\Config;
use Ideal\Core\Db;

/**
 * Абстрактный класс, реализующий основные методы для семейства классов Medium'а
 *
 * Медиумы обеспечивают предоставление данных для Select и SelectMulti, а также их наследников.
 * А также генерируют запросы для сохранения связи многие ко многим в полях вида SelectMulti.
 *
 */
class AbstractModel
{
    /** @var  \Ideal\Core\Admin\Model Модель редактируемого элемента */
    protected $obj;
    /** @var string Название редактируемого поля */
    protected $fieldName;
    /** @var string Название промежуточной таблицы, которая связывает владельца и список элементов */
    protected $table;
    /** @var array Настройки медиума из конфигурационного файла  */
    protected $params;
    /** @var array Список полей в медиум-таблице, если она есть  */
    protected $fields;

    /**
     * @param \Ideal\Core\Admin\Model $obj
     * @param string $fieldName
     * @throws \Exception
     */
    public function __construct($obj, $fieldName)
    {
        $config = Config::getInstance();

        $this->obj = $obj;
        $this->fieldName = $fieldName;

        $parts = preg_split('/[_\\\\]+/', get_class($this));
        $this->table = strtolower($config->db['prefix'] . $parts[0] . '_' . $parts[1] . '_' . $parts[2]);
        $module = $parts[0];
        $module = ($module == 'Ideal') ? '' : $module . '/';
        $structureName = $parts[2];
        $structureFullName = $module . '_' . $structureName;

        $includeFile = $module . 'Medium/' . $structureName . '/config.php';
        $structure = include($includeFile);
        if (!is_array($structure)) {
            throw new \Exception('Не удалось подключить файл: ' . $includeFile);
        }

        $this->params = $structure['params'];
        $this->fields = $structure['fields'];
    }

    /**
     * Получение списка элементов для отображения в select'е или другом поле редактирования
     *
     * @throws \Exception
     * @return array|void
     */
    public function getList()
    {
        throw new \Exception('Вызов в медиуме ' . get_class($this) . ' не переопределённого метода getList');
    }

    /**
     * Получение списка элементов выбранных в SelectMulti для этого владельца
     *
     * @return array Список выбранных элементов
     */
    public function getValues()
    {
        $fieldNames = array_keys($this->fields);
        $ownerField = $fieldNames[0];
        $elementsField = $fieldNames[1];

        $db = Db::getInstance();
        $owner = $this->obj->getPageData();
        $_sql = "SELECT {$elementsField} FROM {$this->table} WHERE {$ownerField}='{$owner['ID']}'";
        $arr = $db->queryArray($_sql);

        $list = array();
        foreach ($arr as $v) {
            $list[] = $v[$elementsField];
        }

        return $list;
    }

    /**
     * Получение дополнительных sql-запросов для сохранения списка выбранных элементов для владельца
     *
     * @param mixed $newValue
     * @return string
     */
    public function getSqlAdd($newValue)
    {
        $fieldNames = array_keys($this->fields);
        $ownerField = $fieldNames[0];
        $elementsField = $fieldNames[1];

        // Удаляем все существующие связи владельца и элементов
        $_sql = "DELETE FROM {$this->table} WHERE {$ownerField}='{{ objectId }}';";

        // Добавляем связи владельца и элементов сделанные пользователем
        foreach ($newValue as $v) {
            $_sql .= "INSERT INTO {$this->table} SET {$ownerField}='{{ objectId }}', {$elementsField}='{$v}';";
        }

        return $_sql;
    }
}
