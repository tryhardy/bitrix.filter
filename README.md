# Пример использования
Формирует filter и runtime поля для \Bitrix\Iblock\ElementTable::getList  
Не работает оператор **!** для множественных свойств
    
```php
<?php
use Tryhardy\BitrixFilter\ElementsFilter;
use \Bitrix\Iblock\ElementTable;

$filter = ElementsFilter::getInstance();
$filter->add('IBLOCK_ID', 1);
$filter->add('ACTIVE', 'Y');
$filter->add('PROPERTY_TAGS', ['tag1', 'tag2']);

//Добавить фильтр по разделу без подразделов
$filter->add('SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID', 1);
$filter->addSection('SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID', 1, false);
//Добавить фильтр по разделу с подразделами
$filter->addSection('SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID', 1, true);

$arRuntime = $filter->getRuntime();
$arFilter = $filter->getFilter();

$dbItems = ElementTable::getList([
    "filter" => $arFilter,
    "select" => [
        //Selected fields'
    ],
    'runtime' => $arRuntime
]);
```

# Composer
```json
{
  "require": {
    "tryhardy/bitrix-filter": "dev-master"
  },
  "repositories": [
    {
      "type": "git",
      "url": "https://github.com/tryhardy/bitrix-filter.git"
    }
  ]
}
```