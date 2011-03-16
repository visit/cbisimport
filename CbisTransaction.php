<?php

/**
 * CBIS Transaction
 *
 * Represents one imported product from CBIS and it's different states
 * through the import process. This data can be used to verify the imported
 * data in it's various states and make sure that the end product (a Drupal
 * node) is valid.
 */
class CbisTransaction {
  // "Singleton" object
  static $current_transaction;

  // Transaction date
  private $id;
  private $time;
  private $language;
  private $states;
  private $path;
  private $nid;
  private $transaction_started;
  private $transaction_id;          // From database
  private $has_load_state;

  /**
   * Returns the current transaction
   *
   * @return CbisTransaction
   * @throws Exception
   *   Throws an exception if no transaction is set to the current one
   */
  public static function getCurrentTransaction() {
    if (!self::$current_transaction) {
      throw new Exception('No current transaction.');
    }
    return self::$current_transaction;
  }

  /**
   * Sets the current transaction
   *
   * Usually a newly constructed instance
   *
   * @param CbisTransaction $t
   * @return void
   */
  public static function setCurrentTransaction(CbisTransaction $t) {
    self::$current_transaction = $t;
  }

  /**
   * Release the current transaction
   */
  public static function releaseCurrentTransaction() {
    self::$current_transaction = NULL;
  }

  /**
   * Constructs a new transaction object with data from an old transaction
   *
   * Used to update a transaction with a valid node state
   *
   * @param $tid
   *   Transaction ID
   * @return CbisTransaction
   * @throws Exception
   *   Throws an exception if a transaction with the given tid can't be found.
   */
  public static function loadTransaction($tid) {
    $record = db_query(
      "SELECT * FROM {cbisimport_transactions} WHERE id = %d",
      array(':tid' => $tid)
    );

    if ($record) {
      // Create the transaction object
      $record = db_fetch_object($record);
      $transaction = new self(
        $record->pid,
        $record->timestamp,
        $record->language
      );

      // Set other data not possible through constructor
      $transaction->setTransactionId($tid);
      $transaction->restoreStates();

      // Set our transaction to the current one and return it
      CbisTransaction::setCurrentTransaction($transaction);
      return $transaction;

    } else {
      throw new Exception('Transcation with id ' . $tid . ' not found.');
    }
  }

  /**
   * Constructs a new transaction
   *
   * @param $id
   *   The CBIS product ID
   * @param $time
   *   Modification date of the product as a UNIX timestamp
   * @param $language
   *   The language of this product
   */
  public function __construct($id, $time, $language) {
    $this->id                  = (int) $id;
    $this->time                = $time;
    $this->language            = $language;
    $this->states              = array();
    $this->transaction_started = time();

    // Create a new directory for this transaction
    $df = file_directory_path();
    $this->path = $df . "/cbis_transactions/{$id}/{$time}_{$language}";
    if (!file_exists($this->path)) {
      mkdir($this->path, 0777, TRUE);
    }
  }

  /**
   * Adds a state to the transaction
   *
   * @param $name
   *   A name for the transaction, will be used as the filename etc.
   * @param $state
   *   The state to save
   * @return this
   *   Allows method chaining
   */
  public function addState($name, $state) {
    $this->states[] = $name;

    if (!is_string($serialize)) {
      $state = json_encode($state);
    }
    $nr_states = count($this->states);
    file_put_contents("{$this->path}/{$nr_states}-{$name}", $state);

    return $this;
  }

  /**
   * Set the related products nid
   *
   * @param $nid
   * @return void
   */
  public function setNid($nid) {
    $this->nid = $nid;
  }

  /**
   * Set the transaction id
   *
   * @param $id
   * @return void
   */
  public function setTransactionId($id) {
    $this->transaction_id = $id;
  }

  /**
   * Update load status
   *
   * @param $has_load_state
   * @return void
   */
  public function setHasLoadState($has_load_state) {
    $this->has_load_state = (bool) $has_load_state;
  }

  /**
   * Restore a states from disk
   *
   * @return $path
   */
  public function restoreStates() {
    try {
      $states = array();
      $di = new DirectoryIterator(realpath(getcwd() . '/' . $this->path));
      foreach ($di as $file) {
        if ($file->isFile() && strpos($file->getFilename(), 'index.json') === FALSE) {
          $parts = explode('-', $file->getFilename());
          $number = array_shift($parts);
          $state_name = join('-', $parts);
          $states[$number] = $state_name;
        }
      }
      // Make sure states are loaded in correct order..
    } catch (Exception $e) {
      // TODO: Do some useful logging or something
    }
  }

  /**
   * Writes transaction information to index file
   *
   * @access private
   * @return void
   */
  private function writeIndex() {
    $index = array(
      'id' => $this->id,
      'nid' => $this->nid,
      'time' => $this->time,
      'language' => $this->language,
      'transaction_started' => $this->transaction_started,
      'states' => $this->states,
      'has_load_state' => $this->has_load_state,
    );
    file_put_contents("{$this->path}/0-index.json", json_encode($index));
  }

  /**
   * Saves the transaction to disk and logs a transaction record in the database
   * Or updates an already logged transaction.
   *
   * @return void
   */
  public function persist() {
    $this->writeIndex();

    // Save database record of this transaction
    $record = (object) array(
      'pid' => $this->id,
      'language' => $this->language,
      'timestamp' => $this->time,
      'has_load_state' => (int) $this->has_load_state
    );

    if ($this->transaction_id) {
      $update = array('id');
      $record->id = $this->transaction_id;
    } else {
      $update = array();
    }

    drupal_write_record('cbisimport_transactions', $record, $update);
  }
}