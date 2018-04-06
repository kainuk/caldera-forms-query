<?php


namespace calderawp\CalderaFormsQuery\Features;


use calderawp\CalderaContainers\Container;
use calderawp\CalderaContainers\Interfaces\ServiceContainer;

use calderawp\CalderaFormsQuery\Delete\DeleteQueryBuilder;
use calderawp\CalderaFormsQuery\Delete\DoesDeleteQuery;
use calderawp\CalderaFormsQuery\DeleteQueries;
use calderawp\CalderaFormsQuery\MySqlBuilder;
use calderawp\CalderaFormsQuery\Delete\Entry as EntryDelete;
use \calderawp\CalderaFormsQuery\Delete\EntryValues as EntryValuesDelete;
use calderawp\CalderaFormsQuery\Select\DoesSelectQuery;
use \calderawp\CalderaFormsQuery\Select\Entry as EntrySelect;
use \calderawp\CalderaFormsQuery\Select\EntryValues as EntryValueSelect;
use calderawp\CalderaFormsQuery\Select\SelectQueryBuilder;
use calderawp\CalderaFormsQuery\SelectQueries;

class FeatureContainer extends Container
{
	/**
	 * @var ServiceContainer
	 */
	protected $serviceContainer;
	/**
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * FeatureContainer constructor.
	 * @param ServiceContainer $serviceContainer
	 * @param \wpdb $wpdb
	 */
	public function __construct(ServiceContainer $serviceContainer, \wpdb $wpdb )
	{

		$this->serviceContainer = $serviceContainer;
		$this->wpdb = $wpdb;
		$this->bindServices();
	}

	/**
	 * Bind services to service container
	 */
	protected function bindServices()
	{
		//@TODO move these to service provider classes
		$this->serviceContainer->singleton( MySqlBuilder::class, function(){
			return new MySqlBuilder();
		});

		$this->serviceContainer->bind( SelectQueries::class, function (){
			//@TODO Factory
			return new SelectQueries(
				new EntrySelect(
					$this->getBuilder(),
					$this->entryTableName()
				),
				new EntryValueSelect(
					$this->getBuilder(),
					$this->entryValueTableName()
				),
				$this->wpdb
			);
		});

		$this->serviceContainer->bind( DeleteQueries::class, function (){
			//@TODO Factory
			return new DeleteQueries(
				new EntryDelete(
					$this->getBuilder(),
					$this->entryTableName()
				),
				new EntryValuesDelete(
					$this->getBuilder(),
					$this->entryValueTableName()
				),
				$this->wpdb
			);
		});

		$this->serviceContainer->singleton( Queries::class, function(){
			return new Queries(
				$this
					->serviceContainer
					->make( SelectQueries::class ),
				$this
					->serviceContainer
					->make(DeleteQueries::class )
			);
		});
	}

	/**
	 * Get MySQL builder
	 *
	 * @return MySqlBuilder
	 */
	public function getBuilder()
	{
		return $this
			->serviceContainer
			->make( MySqlBuilder::class );
	}

	/**
	 * Get query runner
	 *
	 * @return Queries
	 */
	public function getQueries()
	{
		return $this
			->serviceContainer
			->make( Queries::class );
	}

	/**
	 * Select all entries and entry values by user ID
	 *
	 * @param int $userId
	 * @return array
	 */
	public function selectByUserId($userId)
	{
		$query = $this
			->getQueries()
			->entrySelect()
			->queryByUserId($userId);
		return $this->collectResults( $this->select( $query ) );
	}

	/**
	 * Find all entries that have or do not have field with a slug and value
	 *
	 * @param string $fieldSlug Field slug
	 * @param string $fieldValue Field value
	 * @param bool $have Optional. Default: true. If true query is for fields with this value
	 *
	 * @return array
	 */
	public function selectByFieldValue($fieldSlug, $fieldValue, $have = true )
	{

		$type = $have ? 'equals' : 'notEquals';
		$queryForEntryValues = $this
			->getQueries()
			->entryValuesSelect()
			->queryByFieldValue($fieldSlug, $fieldValue, $type, 'AND', [
				'entry_id'
			] );
		$results = $this->select( $queryForEntryValues );
		if( empty( $results ) || 0 >= count( $results )){
			return [];
		}
		foreach ( $results as &$result ){
			$result = $result->entry_id;
		}

		$queryForValues = $this
			->getQueries()
			->entrySelect()
			->queryByEntryIds($results);

		return $this->collectResults( $this->select( $queryForValues ) );
	}

	/**
	 * @return string
	 */
	protected function entryValueTableName()
	{
		return "{$this->wpdb->prefix}cf_form_entry_values";
	}

	/**
	 * @return string
	 */
	protected function entryTableName()
	{
		return "{$this->wpdb->prefix}cf_form_entries";
	}

	/**
	 * Collect results using  Caldera_Forms_Entry_Entry and Caldera_Forms_Entry_Field to represent values
	 *
	 * @param \stdClass[] $entriesValues
	 * @return array
	 */
	private function collectResults($entriesValues)
	{
		$results = [];
		foreach ($entriesValues as $entry) {

			$entry = new \Caldera_Forms_Entry_Entry($entry);
			$query = $this
				->getQueries()
				->entryValuesSelect()
				->queryByEntryId($entry->id);
			$entriesValues = $this->select($query);

			$entryValuesPrepared = $this->collectEntryValues($entriesValues);
			$results[] = [
				'entry' => $entry,
				'values' => $entryValuesPrepared
			];
		}
		return $results;
	}

	/**
	 * Collect entry values as Caldera_Forms_Entry_Field objects
	 *
	 * @param \stdClass[] $entriesValues
	 * @return array
	 */
	private function collectEntryValues($entriesValues): array
	{
		$entryValuesPrepared = [];
		if (!empty($entriesValues)) {
			foreach ($entriesValues as $entryValue) {
				$entryValuesPrepared[] = new \Caldera_Forms_Entry_Field($entryValue);
			}
		}
		return $entryValuesPrepared;
	}

	/**
	 * Do a select query
	 *
	 * @param SelectQueryBuilder $query
	 * @return \stdClass[]
	 */
	private function select(SelectQueryBuilder $query)
	{
		return $this
				->getQueries()
				->select($query);
	}

	/**
	 * Do a delete query
	 *
	 * @param DeleteQueryBuilder $query
	 * @return \stdClass[]
	 */
	private function delete(DeleteQueryBuilder $query)
	{
		return $this->
			getQueries()
			->delete($query);
	}


}