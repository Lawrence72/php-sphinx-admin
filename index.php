<?php


declare(strict_types=1);

session_start();

use Latte\RuntimeException;

include('vendor/autoload.php');
class explorer {
	/**
	 * Change these to suit your server needs
	 */
	const SERVER = "127.0.0.1";
	const USERNAME = '';
	const PASSWORD = '';
	const PORT = 9306;

	const CONFPATH = "";
	const SHOWSERVER = false;
	const RESTRICTQUERIES = true;
	const HISTORY = 100;

	/* ========================== */

	protected $conn;
	protected $current_query;

	/**
	 * Some defaults for the query builder
	 */
	protected $option_types = ['ranker_type', 'ranker_expression',  'ranker_weight', 'max_matches', 'agent_query_timeout', 'boolean_simplify', 'global_idf', 'idf', 'local_df', 'index_weights', 'max_query_time', 'max_predicted_time', 'retry_count', 'retry_delay', 'reverse_scan', 'sort_method', 'rand_seed', 'low_priority'];

	protected $option_sql = ['ranker_type' => 'ranker=', 'ranker_expression' => '', 'ranker_weight' => 'field_weights=', 'max_matches' => 'max_matches=', 'agent_query_timeout' => 'agent_query_timeout=', 'boolean_simplify' =>  'boolean_simplify=', 'global_idf' => 'global_idf=', 'idf' => 'idf=', 'local_df' => 'local_df=', 'index_weights' => 'index_weights=', 'max_query_time' => 'max_query_time=', 'max_predicted_time' => 'max_predicted_time=', 'retry_count' => 'retry_count=', 'retry_delay=', 'reverse_scan=', 'sort_method' => 'sort_method=', 'rand_seed' => 'rand_seed=', 'low_priority' => 'low_priority='];

	protected $post_fields = ['select', 'from', 'where', 'group', 'within', 'having', 'orderby', 'limit', 'facet',];

	protected $sql_fields = ['select' => 'SELECT', 'from' => 'FROM', 'where' => 'WHERE', 'group' => 'GROUP BY', 'within' => 'WITHIN GROUP ORDER BY', 'having' => 'HAVING', 'orderby' => 'ORDER BY', 'limit' => 'LIMIT', 'facet' => 'FACET'];

	protected $build_defaults = ["select" => '', "from" => '', "where" => "", "group" => "", "within" => "", "having" =>  "", "orderby" =>  "", "limit" => "", "facet" =>  "", "ranker_type" =>  "", "ranker_expression" =>  "", "ranker_weight" =>  "", "max_matches" =>  "", "agent_query_timeout" =>  "", "boolean_simplify" => "", "global_idf" => "", "idf" => "", "local_df" =>  "", "index_weights" =>  "", "max_query_time" => "", "max_predicted_time" =>  "", "retry_count" => "", "retry_delay" => "", "reverse_scan" =>  "", "sort_method" => "", "rand_seed" =>  "", "low_priority" => ""];

	public function __construct() {

		try {
			$this->conn = new PDO(
				"mysql:host=" . SELF::SERVER .  ";port=" . SELF::PORT . ";," . SELF::USERNAME . "," . SELF::PASSWORD
			);
		} catch (Exception $e) {
			echo $e->getMessage();
			exit;
		}
	}

	/**
	 * 
	 * @param array $post_data 
	 * @return array 
	 */
	public function setParams(array $post_data): array {


		$tables = $this->getTables();
		$params = ['tables' => $tables];

		$params['showserver'] = SELF::SHOWSERVER;

		if (!empty($post_data['action']) && $post_data['action'] == "buildquery") {
			$params = $this->builder($post_data, $params);
			$_SESSION['builder'] = $params['builder_data'];
			return $params;
		}

		if (empty($_SESSION['builder'])) {
			$params['builder_data'] = $this->build_defaults;
		}

		if (!empty($_SESSION['builder'])) {
			$params['builder_data'] = $_SESSION['builder'];
		}

		$this->current_query = '';
		if (!empty($post_data['action'])) {
			$params = $this->action($post_data, $params);
			$params['current_query'] = $this->current_query;
			if (!empty($this->current_query)) {
				$this->saveHistory($this->current_query);
			}
		}

		return $params;
	}

	/**
	 * 
	 * @param mixed $post_data 
	 * @param mixed $params 
	 * @return array 
	 */
	protected function builder($post_data, $params): array {
		if (empty($post_data['select'])) {
			$post_data['select'] = "*";
		}

		if (empty($post_data['from'])) {
			$post_data['from'] = "table";
		}

		$params = $this->processBuild($post_data, $params);

		return $params;
	}

	/**
	 * 
	 * @param mixed $post_data 
	 * @param mixed $params 
	 * @return array 
	 */
	protected function processBuild($post_data, $params): array {
		$build_query = '';
		$builder_data = [];

		foreach ($this->post_fields as $field) {
			$builder_data[$field] = $post_data[$field];
			if (!empty($post_data[$field])) {
				$build_query .= $this->sql_fields[$field] . ' ' . $post_data[$field] . ' ' . PHP_EOL;
			}
		}

		$options = '';

		foreach ($this->option_types as $option) {
			$builder_data[$option] = $post_data[$option];
			if (!empty($post_data[$option])) {
				$options .= $this->option_sql[$option] . $post_data[$option] . ' ,';
			}
		}
		if (!empty($options)) {
			$options = substr($options, 0, -1);
			$build_query .= 'OPTION ' . $options;
		}

		$params['builder_data'] = $builder_data;
		$params['current_query'] = $build_query;

		return $params;
	}

	/**
	 * 
	 * @param array $params 
	 * @return array 
	 */
	protected function action(array $post_data, array $params): array {
		if (empty($post_data['action'])) {
			return $params;
		}

		switch ($post_data['action']) {
			case 'sql':
				$result = $this->buildSql($post_data, $params);
				$params['results'] = $result['results'];

				break;
			case 'status':
				$params['results'] = $this->getStatus();
				break;
			case 'variables':
				$params['results'] = $this->getVariables();
				break;
			case 'conf':
				$params['results'] = $this->getConf();
				$params['conf'] = true;
				break;
			case 'refresh':
				$params['results'] = $this->refreshSphinx();
				$params['conf'] = true;
				break;
			case 'history':
				$params['history'] = $this->loadHistory();
				break;

			case 'builder':
				$params['builder'] = true;
				break;
			default:
				$params['results'] = "Unsupported Call";
		}

		return $params;
	}

	/**
	 * 
	 * @param array $post_data 
	 * @param array $params 
	 * @return array 
	 */
	protected function buildSql(array $post_data, array $params): array {

		if (!empty($post_data['table'])) {
			return $this->buildTableSql($post_data, $post_data);
		}

		if (!empty($post_data['sql'])) {
			$query = "{$post_data['sql']}";
			$params['results'] = $this->runQuery($query);
			return $params;
		}
		return $params;
	}

	/**
	 * 
	 * @param string $sql 
	 * @return bool 
	 */
	protected function saveHistory(string $sql): bool {
		$trim = $this->trimHistory();
		$sql = preg_replace('/[^[:print:]]/', ' ', $sql);
		$file = fopen(".explorer.log", "a+");
		fwrite($file, $sql . PHP_EOL);
		fclose($file);
		return true;
	}

	/**
	 * 
	 * @return array 
	 */
	protected function loadHistory(): array {
		return array_reverse(file(".explorer.log", FILE_IGNORE_NEW_LINES));
	}

	/**
	 * 
	 * @return bool 
	 */
	protected function trimHistory(): bool {
		if (file_exists('.explorer.log')) {
			$history = file(".explorer.log", FILE_IGNORE_NEW_LINES);
			if (count($history) > SELF::HISTORY) {

				$trim = count($history) - SELF::HISTORY;
				$trimmed_history = "";

				for ($i = $trim; $i < count($history); $i++) {
					$trimmed_history .= $history[$i] . PHP_EOL;
				}

				$file = fopen(".explorer.log", "w");
				fwrite($file, $trimmed_history);
				fclose($file);
			}
		}
		return true;
	}

	/**
	 * 
	 * @param array $post_data 
	 * @param array $params 
	 * @return array 
	 */
	protected function buildTableSql(array $post_data, array $params): array {
		$pattern  = '/^[a-zA-Z0-9_]/';
		if (!empty($post_data['table'])) {
			$table = preg_replace($pattern, $post_data['table'], 'a');
		}

		if (empty($table)) {
			return $params;
		}

		$limit = !empty($post_data['limit']) ? ' LIMIT ' . intval($post_data['limit']) : '';
		$query = "SELECT * FROM {$table} {$limit}";
		$params['results'] = $this->runQuery($query);
		return $params;
	}

	/**
	 * 
	 * @return array 
	 */
	protected function getTables(): array {
		$query = "SHOW TABLES";
		$results = $this->runQuery($query);
		return $results;
	}

	/**
	 * 
	 * @return array 
	 */
	protected function getStatus(): array {
		$query = "SHOW STATUS";
		$results = $this->runQuery($query);
		return $results;
	}

	/**
	 * 
	 * @return array 
	 */
	protected function getVariables(): array {
		$query = "SHOW VARIABLES";
		$results = $this->runQuery($query);
		return $results;
	}

	/**
	 * 
	 * @return string 
	 */
	protected function refreshSphinx(): string {
		if (!SELF::SHOWSERVER) {
			return "Conf file is unavailable";
		}
		return "sudo /usr/bin/indexer --rotate --config " . SELF::CONFPATH . " --all";
	}

	/**
	 * 
	 * @return string 
	 */
	protected function getConf(): string {
		if (!is_file(SELF::CONFPATH) || !SELF::SHOWSERVER) {
			return 'Conf file is unavailable';
		}

		return file_get_contents(SELF::CONFPATH);
	}
	/**
	 * 
	 * @param string $sql 
	 */
	protected function runQuery(string $sql) {
		$sql = trim($sql);
		if (!$this->validateSql($sql)) {
			return "This query is not allowed";
		}
		$this->current_query = $sql;
		$query = $this->conn->prepare($sql);
		try {
			$query->execute();

			$results = $query->fetchAll(PDO::FETCH_ASSOC);

			if (empty($results)) {
				return [];
			}
			return $this->processResults($results);
		} catch (PDOException $e) {
			return $e->getMessage();
		}
	}
	/**
	 * 
	 * @param string $sql 
	 * @return bool 
	 */
	protected function validateSql(string $sql): bool {
		if (self::RESTRICTQUERIES && !preg_match("/^SELECT|SHOW/i", $sql)) {
			return false;
		}

		if (!SELF::SHOWSERVER && self::RESTRICTQUERIES && preg_match("/^SHOW/i", $sql) && !preg_match("/^SHOW TABLES/i", $sql)) {
			return false;
		}
		return true;
	}

	/**
	 * 
	 * @param array $results 
	 * @return array 
	 */
	protected function processResults(array $results): array {
		foreach ($results as $result) {
			$headings = array_keys($result);
			$body[] = array_values($result);
		}

		return ['headings' => $headings, 'body' => $body];
	}

	/**
	 * 
	 * @param string $template 
	 * @param array $params 
	 * @return string 
	 * @throws LogicException 
	 * @throws RuntimeException 
	 * @throws Throwable 
	 */
	public function render(string $template, array $params): string {

		$latte = new \Latte\Engine;

		$latte->setTempDirectory(sys_get_temp_dir());

		return $latte->renderToString(__DIR__ . "/" .  $template . '.latte', $params);
	}
}


$post_data = !empty($_POST) ? $_POST : [];
$explorer = new explorer;
$params = $explorer->setParams($post_data);

echo $explorer->render('index', $params);
