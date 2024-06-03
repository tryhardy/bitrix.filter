<?php

namespace Tryhardy\BitrixFilter\Traits;

use Bitrix\Main\Loader;
use Exception;
use Tryhardy\BitrixFilter\ElementsFilter;

trait FilterTrait
{
	protected static self $instance;

	protected array $filter = [];       //массив полей для filter
	protected array $runtime = [];      //массив полей для runtime
	protected array $resources = [];    //реестр для хранения ассоциаций между runtime и filter

	/**
	 * @throws Exception
	 */
	protected function __construct()
	{
		if (!Loader::includeModule("iblock")) {
			throw new Exception("Module iblock is not installed");
		}
	}

	public static function getInstance(bool $fromStatic = true) : self
	{
		return new static();
	}

	/**
	 * Get filter param by key
	 * @param string $key
	 * @return mixed|null
	 */
	public function get(string $key)
	{
		if (isset($this->filter[$key])) {
			return $this->filter[$key];
		}

		return null;
	}

	/**
	 * get runtime params
	 * @return array
	 */
	public function getRuntime() : array
	{
		return $this->runtime;
	}

	/**
	 * get filter params
	 * @return array
	 */
	public function getFilter() : array
	{
		return $this->filter;
	}

	protected static function getMatches(string $key, $condition) : array
	{
		$matches = [];
		preg_match($condition, $key, $matches);
		return $matches;
	}

	/**
	 * Set filter param by key
	 * @param string $key
	 * @param $value
	 * @return $this
	 */
	public function add(string $key, $value) : self
	{
		$this->filter[$key] = $value;
		return $this;
	}

	/**
	 * Remove param from filter by key
	 * @param string $key
	 * @return ElementsFilter
	 */
	public function remove(string $key) : self
	{
		//очищаем runtime и filter по ключу, если в эти массивы попали пользовательские свойства
		if ($this->resources[$key]) {
			foreach($this->resources[$key] as $variableName => $resource) {
				foreach($resource as $code) {
					if ($variableName === 'runtime') {
						unset($this->runtime[$code]);
					}
					elseif($variableName === 'filter') {
						unset($this->filter[$code]);
					}
				}
			}

			unset($this->resources[$key]);
		}
		else {
			unset($this->filter[$key]);
		}

		return $this;
	}
}
