<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStoredToStatementRoot extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		set_time_limit(0);

		$db = \DB::getMongoDB();
    $statementsCollection = new MongoCollection($db, 'statements');
    
    $statementsCollection->createIndex(['stored' => -1]);
    $statementsCollection->createIndex(['lrs_id' => 1, 'stored' => -1]);

    $statementsCursor = $statementsCollection->find();

    $remaining = $statementsCursor->count();
    print($remaining . ' statements total' . PHP_EOL);

    $maxBatchSize = 10000;

    while($statementsCursor->hasNext()) {
	    $batch = new MongoUpdateBatch($statementsCollection);
	    $batchSize = 0;

	    while($batchSize < $maxBatchSize && $statementsCursor->hasNext()) {
	    	$batchSize++;
	    	$statement = $statementsCursor->next();
	    	
	    	$query = [
				  'q' => ['_id' => $statement['_id']],
				  'u' => ['$set' => ["stored" => new \MongoDate(strtotime($statement['statement']['stored']))]],
				  'multi' => false,
				  'upsert' => false,
				];

	    	if(isset($statement['refs'])) {
	    		foreach ($statement['refs'] as $key => $refStatement) {
	    			if(isset($refStatement['timestamp'])) $query['u']['$set']['statement.refs.'.$key.'.timestamp'] = new \MongoDate(strtotime($refStatement['timestamp']));
	    			if(isset($refStatement['stored'])) $query['u']['$set']['statement.refs.'.$key.'.stored'] = new \MongoDate(strtotime($refStatement['stored']));
	    		}
	    	}

				$batch->add((object) $query);
	    }
	    $batch->execute();
	    $remaining -= $batchSize;

	    print($remaining . ' remaining' . PHP_EOL);
    }
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		$db = \DB::getMongoDB();
    $statements = new MongoCollection($db, 'statements');
    
    $statements->deleteIndex('stored');
    $statements->deleteIndex(['lrs_id' => 1, 'stored' => -1]);

    $statements->update([], ['$unset' => ["stored" => ""]], ['multiple' => true]);
	}

}
