<?php

require_once('db.php');
require_once('../model/book.php');
require_once('../model/response.php');

// attempt to set up connections to read and write db connections
try {
  $writeDB = DB::connectWriteDB();
  $readDB = DB::connectReadDB();
}
catch(PDOException $ex) {
  // log connection error for troubleshooting and return a json error response
  error_log("Connection Error: ".$ex, 0);
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("Database connection error");
  $response->send();
  exit;
}

// check if bookid is in the url e.g. /bookid/1
if (array_key_exists("bookid",$_GET)) {
  // get book id from query string
  $bookid = $_GET['bookid'];

  //check to see if book id in query string is not empty and is number, if not return json error
  if($bookid == '' || !is_numeric($bookid)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Book ID cannot be blank or must be numeric");
    $response->send();
    exit;
  }
  // if request is a GET, e.g. get task
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // attempt to query the database
    try {
      // create db query
      // ADD AUTH TO QUERY
      $query = $readDB->prepare('SELECT * from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
  		$query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned book
      $bookArray = array();

      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Book not found");
        $response->send();
        exit;
      }

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create task and store in array for return in json data
  	    $bookArray[] = $book->returnBookAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['no. of books'] = $rowCount;
      $returnData['books'] = $bookArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setBookInfo($returnData);
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch(BookException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    catch(PDOException $ex) {
      error_log("Database Query Error: ".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to get book");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET, PATCH, DELETE is used then return 405 method not allowed
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}
// handle getting all tasks or creating a new one
elseif(empty($_GET)) {

  // if request is a GET e.g. get tasks
  if($_SERVER['REQUEST_METHOD'] === 'GET') {

    // attempt to query the database
    try {
      // ADD AUTH TO QUERY
      // create db query
      $query = $readDB->prepare('SELECT * from books');
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned tasks
      $bookArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create task and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['no. of books'] = $rowCount;
      $returnData['books'] = $bookArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setBookInfo($returnData);
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch(BookException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    catch(PDOException $ex) {
      error_log("Database Query Error: ".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }
  }
  // else if request is a POST e.g. create task
  elseif($_SERVER['REQUEST_METHOD'] === 'POST') {

    // create task
    try {
      // check request's content type header is JSON
      if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not set to JSON");
        $response->send();
        exit;
      }

      // get POST request body as the POSTed data will be JSON format
      $rawPostData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPostData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
      }

      // check if post request contains title and completed data in body as these are mandatory
      if(!isset($jsonData->category) || !isset($jsonData->title) || !isset($jsonData->pagecount) || !isset($jsonData->language)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->category) ? $response->addMessage("Category field is mandatory and must be provided") : false);
        (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
        (!isset($jsonData->pagecount) ? $response->addMessage("Page Count field is mandatory and must be provided") : false);
        (!isset($jsonData->language) ? $response->addMessage("Language field is mandatory and must be provided") : false);
        $response->send();
        exit;
      }

      //Generate Random etag
      $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $etag = '';
      for ($i = 0; $i < 10; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $etag .= $characters[$index];
      }
      // create new task with data, if non mandatory fields not provided then set to null
      $newBook = new Book(null, $jsonData->category, $jsonData->title, $jsonData->pagecount, $jsonData->language, $etag);
      // get title, description, deadline, completed and store them in variables
      $category = $newBook->getCategory();
      $title = $newBook->getTitle();
      $pagecount = $newBook->getPageCount();
      $language = $newBook->getLanguage();

      // ADD AUTH TO QUERY
      // create db query
      $query = $writeDB->prepare('insert into books (category, title, pagecount, language, etag) values (:category, :title, :pagecount, :language, :etag)');
      $query->bindParam(':category', $category, PDO::PARAM_STR);
      $query->bindParam(':title', $title, PDO::PARAM_STR);
      $query->bindParam(':pagecount', $pagecount, PDO::PARAM_STR);
      $query->bindParam(':language', $language, PDO::PARAM_STR);
      $query->bindParam(':etag', $etag, PDO::PARAM_STR);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if row was actually inserted, PDO exception should have caught it if not.
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to add new book");
        $response->send();
        exit;
      }

      // get last task id so we can return the Task in the json
      $lastBookID = $writeDB->lastInsertId();
      // ADD AUTH TO QUERY
      // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
      $query = $writeDB->prepare('SELECT id, category, title, pagecount, language, etag from books where id = :bookid');
      $query->bindParam(':bookid', $lastBookID, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the new task was returned
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to retrieve task after creation");
        $response->send();
        exit;
      }

      // create empty array to store tasks
      $bookArray = array();

      // for each row returned - should be just one
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $task = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create task and store in array for return in json data
        $bookArray[] = $task->returnBookAsArray();
      }
      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['no. of books'] = $rowCount;
      $returnData['books'] = $bookArray;

      //set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(201);
      $response->setSuccess(true);
      $response->addMessage("Book created");
      $response->setBookInfo($returnData);
      $response->send();
      exit;
    }
    // if task fails to create due to data types, missing fields or invalid data then send error json
    catch(TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch(PDOException $ex) {
      error_log("Database Query Error: ".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to insert new book into database - check submitted data for errors");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET or POST is used then return 405 method not allowed
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}
