<?php
namespace Tryhardy\BitrixFilter;

use Tryhardy\BitrixFilter\Traits\RegistryTrait;

/**
 * Simple ORM iblock elements bitrix filter
 */
class ElementsFilter
{
	use RegistryTrait;

	/**
	 * Set value to filter
	 * @param string $key
	 * @param $value
	 * @return $this
	 */
	public function set(string $key, $value) : ElementsFilter
	{
		//Check if provided key is a property name
		list($propertyFilter, $propertyRuntime) = $this->setPropertyToFilter($key, $value);

		if (!empty($propertyFilter)) {
			$this->filter = array_merge($this->filter, $propertyFilter);
		}

		if (!empty($propertyRuntime)) {
			$this->runtime = array_merge($this->runtime, $propertyRuntime);
		}

		if (empty($propertyFilter) || empty($propertyRuntime)) {
			$this->filter[$key] = $value;
		}

		return $this;
	}

	/**
	 * Helps to find elements by section
	 * @param int $sectionId
	 * @param bool $includeSubsections
	 * @return $this
	 */
	public function setSection(int $sectionId, bool $includeSubsections = true) : ElementsFilter
	{
		return $this;
	}

	/**
	 * Формирует поля runtime и filter для запроса,
	 * если в фильтр попадают пользовательские свойства элемента инфоблока
	 * @param string $code
	 * @param $value
	 * @return array[]
	 */
	protected function setPropertyToFilter(string $code, $value) : array
	{
		$fullCode = $code;
		$arRuntime = [];
		$arFilter = [];
		$contains = "/^(\?[=%]?|=[%=]?|%[=]?|>[<=]?|<[>=]?|![=@%]?(><|=%|%=|==)?|@|)(PROPERTY_|)(.*)$/";
		$matches = [];

		preg_match($contains, $code, $matches);
		$startsWith = (bool) $matches[3]; //Убеждаемся, что это точно фильтр по свойству

		if ($startsWith) {
			$condition = $matches[1]; //Логические операторы
			$minifiedCode = $matches[4]; //Имя свойства без PROPERTY_

			$runtimeCode = $minifiedCode . '_CODE';
			$runtimeValue = $minifiedCode . '_VALUE';

			$arFilter[$runtimeCode . '.ACTIVE'] = 'Y';
			$arFilter[$runtimeCode . '.CODE'] = $minifiedCode;
			$arFilter[$condition . $runtimeValue . '.VALUE'] = $value;

			$arRuntime[$runtimeCode] = [
				'data_type' => '\Bitrix\Iblock\PropertyTable',
				'reference' => [
					'=this.IBLOCK_ID' => 'ref.IBLOCK_ID',
				],
				'join_type' => "LEFT"
			];

			$arRuntime[$runtimeValue] = [
				'data_type' => '\Bitrix\Iblock\ElementPropertyTable',
				'reference' => [
					'=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
					'=this.'.$runtimeCode.'.ID' => 'ref.IBLOCK_PROPERTY_ID',
				],
				'join_type' => "LEFT"
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
		}

		return [$arFilter, $arRuntime];
	}
}