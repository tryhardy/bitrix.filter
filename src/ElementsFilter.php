<?php
namespace Tryhardy\BitrixFilter;

use Bitrix\Iblock\SectionTable;
use Exception;
use Tryhardy\BitrixFilter\Traits\FilterTrait;

/**
 * Simple ORM iblock elements bitrix filter
 */
class ElementsFilter
{
	use FilterTrait;

	private string $sectionMatchCondition = '/^(=[%=]?|%[=]?|>[<=]?|<[>=]?|![=@%]?(><|=%|%=|==)?|@|)(SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID)/';
	private string $propertyMatchCondition = '/^(\?[=%]?|=[%=]?|%[=]?|>[<=]?|<[>=]?|![=@%]?(><|=%|%=|==)?|@|)(PROPERTY_|)(.*)$/';

	/**
	 * Set value to filter
	 * @param string $key
	 * @param $value
	 * @return $this
	 * @throws Exception
	 */
	public function add(string $key, $value) : ElementsFilter
	{
		$filter = [];
		$runtime = [];

		//Формируем фильтр по свойствам
		if ($this->checkFieldFilter($key, $this->propertyMatchCondition)) {
			list($filter, $runtime) = $this->setProperty($key, $value);
		}

		//Формируем фильтр по разделам
		if ($this->checkFieldFilter($key, $this->sectionMatchCondition)) {
			list($filter, $runtime) = $this->setSections($key, $value);
		}

		return $this->setToFilter($key, $value, $filter, $runtime);
	}

	/**
	 * Set section value to filter (with subsections)
	 * @param string $key
	 * @param $value
	 * @param bool $includeSubsections
	 * @return $this
	 * @throws Exception
	 */
	public function addSections(string $key, $value, bool $includeSubsections = false) : ElementsFilter
	{
		$filter = [];
		$runtime = [];

		//Формируем фильтр по разделам
		if ($this->checkFieldFilter($key, $this->sectionMatchCondition)) {
			list($filter, $runtime) = $this->setSections($key, $value, $includeSubsections);
		}

		return $this->setToFilter($key, $value, $filter, $runtime);
	}

	/**
	 * Формирует runtime и filter для поиска по разделу (или группе разделов)
	 * В метод $filter->set() передается SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID
	 * @param string $code
	 * @param int|int[] $value
	 * @param bool $includeSubsections
	 * @return array
	 * @throws Exception
	 */
	private function setSections(string $code, $value, bool $includeSubsections = false) : array
	{
		$fullCode = $code;
		$matches = $this->getMatches($code, $this->sectionMatchCondition);
		$arRuntime = [];
		$arFilter = [];

		//Проверяем, передается ли ID инфоблока (т.к. для поиска по разделам делаются подзапросы)
		$iblockId = (int) $this->get('IBLOCK_ID');
		if (!$iblockId) throw new Exception('IBLOCK_ID is not set in filter. Use set() method');

		$sectionCondition = $matches[1];

		//Если выборка по одному разделу, включая дочерние подразделы
		if ($includeSubsections && !is_array($value)) {
			$DBSection = SectionTable::getByPrimary($value, [
				'filter' => [
					'IBLOCK_ID' => $iblockId,
					'ACTIVE' => 'Y'
				],
				'select' => ['LEFT_MARGIN', 'RIGHT_MARGIN'],
			])->fetch();

			//Есть ли в фильтре условие отрицания (!)
			if (stripos($sectionCondition, '!') !== false) {
				$arFilter[] = [
					'LOGIC' => 'OR',
					"<IBLOCK_SECTION.LEFT_MARGIN" => $DBSection['LEFT_MARGIN'],
					'>IBLOCK_SECTION.RIGHT_MARGIN' => $DBSection['RIGHT_MARGIN'],
				];
			}
			else {
				$arFilter = [
					'>=IBLOCK_SECTION.LEFT_MARGIN' => $DBSection['LEFT_MARGIN'],
					'<=IBLOCK_SECTION.RIGHT_MARGIN' => $DBSection['RIGHT_MARGIN'],
				];
			}
		}
		//Если выборка по нескольким разделам, включая дочерние подразделы
		elseif ($includeSubsections && is_array($value)) {
			$DBSection = SectionTable::getList([
				'filter' => [
					'IBLOCK_ID' => $iblockId,
					'ACTIVE' => 'Y',
					'ID' => $value
				],
				'select' => ['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'],
			])->fetchAll();

			$sectionFilter = [];
			//Есть ли в фильтре условие отрицания (!)
			if (stripos($sectionCondition, '!') !== false) {
				$leftMargins = [];
				$rightMargins = [];
				foreach($DBSection as $section) {
					$leftMargins[] = $section['LEFT_MARGIN'];
					$rightMargins[] = $section['RIGHT_MARGIN'];
				}

				if (count($DBSection) > 0) {
					$sectionFilter = [
						'LOGIC' => 'OR',
						[
							'!><IBLOCK_SECTION.LEFT_MARGIN' => [min($leftMargins), max($leftMargins)],
							'!><IBLOCK_SECTION.RIGHT_MARGIN' => [min($rightMargins), max($rightMargins)],
						],
						[
							'IBLOCK_SECTION.ID' => null
						]
					];
				}
			}
			else {
				if (count($DBSection) > 0) {
					$sectionFilter['LOGIC'] = 'OR';
				}

				foreach($DBSection as $section) {
					$sectionFilter[] = [
						'>=IBLOCK_SECTION.LEFT_MARGIN' => $section['LEFT_MARGIN'],
						'<=IBLOCK_SECTION.RIGHT_MARGIN' => $section['RIGHT_MARGIN'],
					];
				}
			}

			if (!empty($sectionFilter)) {
				$arFilter[] = $sectionFilter;
			}
		}
		//Если выборка по одному или нескольким разделам без подразделов
		else {
			$arFilter[$sectionCondition.'IBLOCK_SECTION.ID'] = $value;
		}

		return [$arFilter, $arRuntime];
	}

	/**
	 * Формирует runtime и filter,
	 * если в фильтр попадают пользовательские свойства элемента инфоблока
	 * @param string $code
	 * @param $value
	 * @return array[]
	 */
	protected function setProperty(string $code, $value) : array
	{
		$fullCode = $code;
		$contains = "/^(\?[=%]?|=[%=]?|%[=]?|>[<=]?|<[>=]?|![=@%]?(><|=%|%=|==)?|@|)(PROPERTY_|)(.*)$/";
		$matches = [];

		preg_match($contains, $code, $matches);

		$condition = $matches[1]; //Логические операторы
		$minifiedCode = $matches[4]; //Имя свойства без PROPERTY_

		$runtimeCode = $minifiedCode . '_CODE';
		$runtimeValue = $minifiedCode . '_VALUE';

		$arFilter = [
			$runtimeCode . '.ACTIVE' => 'Y',
			$runtimeCode . '.CODE' => $minifiedCode,
			$condition . $runtimeValue . '.VALUE' => $value
		];

		$arRuntime = [
			$runtimeCode => [
				'data_type' => '\Bitrix\Iblock\PropertyTable',
				'reference' => [
					'=this.IBLOCK_ID' => 'ref.IBLOCK_ID',
				],
				'join_type' => "LEFT"
			],
			$runtimeValue => [
				'data_type' => '\Bitrix\Iblock\ElementPropertyTable',
				'reference' => [
					'=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
					'=this.'.$runtimeCode.'.ID' => 'ref.IBLOCK_PROPERTY_ID',
				],
				'join_type' => "LEFT"
			]
		];

		$this->resources[$fullCode] = [
			'filter' => [
				$runtimeCode . '.ACTIVE',
				$runtimeCode . '.CODE',
				$condition . $runtimeValue . '.VALUE'
			],
			'runtime' => [
				$runtimeCode,
				$runtimeValue
			]
		];

		return [$arFilter, $arRuntime];
	}

	/**
	 * @param string $key
	 * @param $value
	 * @param array $filter
	 * @param array $runtime
	 * @return ElementsFilter
	 */
	private function setToFilter(string $key, $value, array $filter = [], array $runtime = []) : ElementsFilter
	{
		if (!empty($filter) && !empty($runtime)) {
			$this->filter = array_merge($this->filter, $filter);
			$this->runtime = array_merge($this->runtime, $runtime);
		}
		else {
			$this->filter[$key] = $value;
		}

		return $this;
	}


	/**
	 * @param string $key
	 * @param string $condition
	 * @return bool
	 */
	private function checkFieldFilter(string $key, string $condition) : bool
	{
		$matches = $this->getMatches($key, $condition);

		if (!empty($matches)) {
			return (bool) $matches[3];
		}

		return false;
	}
}