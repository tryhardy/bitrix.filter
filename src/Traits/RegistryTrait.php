<?php

namespace Tryhardy\BitrixFilter\Traits;

trait RegistryTrait
{
	protected static self $instance;
	protected array $filter = [];
	protected array $runtime = [];
	protected array $resources = [];

	protected function __construct()
	{}

	public static function getInstance() : self
	{
		if (!isset(self::$instance)) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Get filter param by key
	 * @param $key
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

	/**
	 * Set filter param by key
	 * @param string $key
	 * @param $value
	 * @return $this
	 */
	public function set(string $key, $value) : self
	{
		$this->filter[$key] = $value;
		return $this;
	}

	/**
	 * Remove param from filter by key
	 * @param string $key
	 * @return $this
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
		}
		else {
			unset($this->filter[$key]);
		}

		return $this;
	}
}