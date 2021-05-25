<?php
declare(strict_types=1);

namespace YaPro\SymfonyCache\Tests\Functional\Vendor\Symfony\Component\Cache\Adapter;

use Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use function sleep;
use const PHP_INT_MAX;

/**
 * Если isHit() возвращает false, метод get() ДОЛЖЕН возвращать null. Обратите внимание, что null является
 * допустимым кэшированным значением, поэтому метод isHit() СЛЕДУЕТ использовать для
 * различения "значение null было найдено" и "значение не было найдено"."
 *
 * $beta - частота автоматического обновления кэша (иногда говорят вероятность раннего истечения срока действия):
 * 0 - отключает $beta (отключить раннее повторное вычисление)
 * INF - срабатывает немедленное истечение срока действия кэша
 * Если не указывать, то значение по умолчанию (или предоставление null) зависит от реализации, но обычно 1.0,
 * что должно обеспечить оптимальную защиту от панического обновления кэша.
 * см. https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration
 *
 *
 * Тест на тестирование beta https://symfony.com/doc/current/components/cache.html#cache-contracts
 * Получаем данные из getDataFromDb
 * создаем кэш с временем жизни 2 секунды + beta равным 0 (0 - данные всегда берутся из кэша)
 * ждем 1 секунду
 * получаем значение - данные получены из кэша
 * ждем 1 секунду
 * получаем значение - данных нет (кэш протух)
 * создаем кэш с временем жизни 2 секунды + beta равным 2 (2 - очень раннее обновление кэша)
 * ждем 1 секунду
 * получаем значение - данных нет (спасибо beta) - нужно сходить за данными в getDataFromDb
 */
class FilesystemAdapterTest extends TestCase
{
	const FIRST_VALUE = 'firstValue';
	const SECOND_VALUE = 'secondValue';
	const UNIQ_CACHE_KEY = 'my_cache_key';

	private $valueInDb = null;
	/**
	 * @var FilesystemAdapter
	 */
	protected static $cache;

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();
		self::$cache = new FilesystemAdapter();
	}

	private function dataProvider(): Generator
	{
		yield self::FIRST_VALUE;
		yield self::SECOND_VALUE;
		return null;
	}

	public function testCacheDieAfterOneSecond()
	{
		self::$cache->delete(self::UNIQ_CACHE_KEY);

		$this->valueInDb = 1;
		$providerForNonExistentCacheData = function (ItemInterface $item) {
			$item->expiresAfter(1);
			return $this->valueInDb;
		};
		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData);
		$this->assertSame($this->valueInDb, $value);

		sleep(1);
		$this->assertFalse(self::$cache->hasItem(self::UNIQ_CACHE_KEY));

		$item = self::$cache->getItem(self::UNIQ_CACHE_KEY);
		$this->assertSame(null, $item->get());
	}

	public function testCacheForeverAlive()
	{
		$providerForNonExistentCacheData = function (ItemInterface $item) {
			// null - кэш не протухает
			$item->expiresAfter(null);
			return PHP_INT_MAX;
		};
		self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData);

		sleep(1);
		$this->assertTrue(self::$cache->hasItem(self::UNIQ_CACHE_KEY));
		$item = self::$cache->getItem(self::UNIQ_CACHE_KEY);
		$this->assertSame(PHP_INT_MAX, $item->get());
	}

	public function testBeta0()
	{
		self::$cache->delete(self::UNIQ_CACHE_KEY);

		// 0 - данные всегда берутся из кэша (если они есть в кэше, а если нет, данные снова запрашиваются из провайдера)
		$beta = 0;
		$this->assertSame(0, $beta ?? 1.0);

		$this->valueInDb = 1;
		$providerForNonExistentCacheData = function (ItemInterface $item) {
			$item->expiresAfter(2);
			return $this->valueInDb;
		};
		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData, $beta);
		$this->assertSame($this->valueInDb, $value);

		$this->valueInDb = 2;

		sleep(1);
		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData, $beta);
		$this->assertSame(1, $value);

		sleep(1);
		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData, $beta);
		$this->assertSame(2, $value);
	}

	public function testBeta2()
	{
		self::$cache->delete(self::UNIQ_CACHE_KEY);

		// $beta устанавливаем вероятность обновления кэша (чем больше это значение, тем чаще кэш будет обновляться)
		// важно: это вовсе не секунды до момента протухания кэша (если нужны секунды - нужна реализация своими силами)
		$beta = 100000.0;
		$this->valueInDb = 1;
		$providerForNonExistentCacheData = function (ItemInterface $item) {
			$item->expiresAfter(2);
			return $this->valueInDb;
		};

		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData, $beta);
		$this->assertSame($this->valueInDb, $value);

		$this->valueInDb = 2;

		sleep(1);
		$value = self::$cache->get(self::UNIQ_CACHE_KEY, $providerForNonExistentCacheData, $beta);
		$this->assertSame(2, $value);
	}
}
