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
if(array_key_exists("bookid",$_GET)) {
  // get book id from query string
  $bookid = $_GET['bookid'];

  //check to see if book id in query string is not empty and is number, if not return json error
  if($bookid === '' || !is_numeric($bookid)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Book ID cannot be blank or must be numeric");
    $response->send();
    exit;
  }
  // handle getting all books or creating a new one
  // if request is a GET, e.g. get book
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

      // create book array to store returned book
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
        // create new book object for each row
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create book and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }

      // bundle books and rows returned into an array to return in the json data
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
  elseif($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // update book
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

      // get PATCH request body as the PATCHed data will be JSON format
      $rawPatchData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPatchData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
      }

      // set book field updated to false initially
      $category_updated = false;
      $title_updated = false;
      $pagecount_updated = false;
      $language_updated = false;

      // create blank query fields string to append each field to
      $queryFields = "";

      // check if title exists in PATCH
      if(isset($jsonData->category)) {
        // set title field updated to true
        $category_updated = true;
        // add title field to query field string
        $queryFields .= "category = :category, ";
      }

      // check if description exists in PATCH
      if(isset($jsonData->title)) {
        // set description field updated to true
        $title_updated = true;
        // add description field to query field string
        $queryFields .= "title = :title, ";
      }

      // check if deadline exists in PATCH
      if(isset($jsonData->pagecount)) {
        // set deadline field updated to true
        $pagecount_updated = true;
        // add deadline field to query field string
        $queryFields .= "pagecount = :pagecount, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->language)) {
        // set completed field updated to true
        $language_updated = true;
        // add completed field to query field string
        $queryFields .= "language = :language, ";
      }

      // remove the right hand comma and trailing space
      $queryFields = rtrim($queryFields, ", ");

      // check if any book fields supplied in JSON
      if($category_updated === false && $title_updated === false && $pagecount_updated === false && $language_updated === false) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("No book fields provided");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to get book from database to update - use master db
      $query = $writeDB->prepare('SELECT * from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the book exists for a given book id
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No book found to update");
        $response->send();
        exit;
      }

      // for each row returned - should be just one
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);
      }
      // ADD AUTH TO QUERY
      // create the query string including any query fields
      $queryString = "update books set ".$queryFields." where id = :bookid";
      // prepare the query
      $query = $writeDB->prepare($queryString);

      // if title has been provided
      if($category_updated === true) {
        // set book object title to given value (checks for valid input)
        $book->setCategory($jsonData->category);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_category = $book->getCategory();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':category', $up_category, PDO::PARAM_STR);
      }

      // if description has been provided
      if($title_updated === true) {
        // set book object description to given value (checks for valid input)
        $book->setTitle($jsonData->title);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_title = $book->getTitle();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':title', $up_title, PDO::PARAM_STR);
      }

      // if deadline has been provided
      if($pagecount_updated === true) {
        // set book object deadline to given value (checks for valid input)
        $book->setPageCount($jsonData->pagecount);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_pagecount = $book->getPageCount();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':pagecount', $up_pagecount, PDO::PARAM_STR);
      }

      // if completed has been provided
      if($language_updated === true) {
        // set book object completed to given value (checks for valid input)
        $book->setLanguage($jsonData->language);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_language= $book->getLanguage();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':language', $up_language, PDO::PARAM_STR);
      }

      // bind the book id provided in the query string
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      // run the query
      $query->execute();

      // get affected row count
      $rowCount = $query->rowCount();

      // check if row was actually updated, could be that the given values are the same as the stored values
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Book not updated - given values may be the same as the stored values");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to return the newly edited book - connect to master database
      $query = $writeDB->prepare('SELECT * from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if book was found
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No Book found");
        $response->send();
        exit;
      }
      // create book array to store returned books
      $bookArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object for each row returned
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create book and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }
      // bundle books and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['books'] = $bookArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Book updated");
      $response->setBookInfo($returnData);
      $response->send();
      exit;
    }
    catch(bookException $ex) {
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
      $response->addMessage("Failed to update Book - check your data for errors");
      $response->send();
      exit;
    }
  }
  elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    // update book
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

      // get PATCH request body as the PATCHed data will be JSON format
      $rawPatchData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPatchData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
      }

      // set book field updated to false initially
      $category_updated = false;
      $title_updated = false;
      $pagecount_updated = false;
      $language_updated = false;

      // create blank query fields string to append each field to
      $queryFields = "";

      // check if title exists in PATCH
      if(isset($jsonData->category)) {
        // set title field updated to true
        $category_updated = true;
        // add title field to query field string
        $queryFields .= "category = :category, ";
      }

      // check if description exists in PATCH
      if(isset($jsonData->title)) {
        // set description field updated to true
        $title_updated = true;
        // add description field to query field string
        $queryFields .= "title = :title, ";
      }

      // check if deadline exists in PATCH
      if(isset($jsonData->pagecount)) {
        // set deadline field updated to true
        $pagecount_updated = true;
        // add deadline field to query field string
        $queryFields .= "pagecount = :pagecount, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->language)) {
        // set completed field updated to true
        $language_updated = true;
        // add completed field to query field string
        $queryFields .= "language = :language, ";
      }

      // remove the right hand comma and trailing space
      $queryFields = rtrim($queryFields, ", ");

      // check if any book fields supplied in JSON
      if($category_updated === false && $title_updated === false && $pagecount_updated === false && $language_updated === false) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("No book fields provided");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to get book from database to update - use master db
      $query = $writeDB->prepare('SELECT * from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the book exists for a given book id
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No book found to update");
        $response->send();
        exit;
      }

      // for each row returned - should be just one
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);
      }
      // ADD AUTH TO QUERY
      // create the query string including any query fields
      $queryString = "update books set ".$queryFields." where id = :bookid";
      // prepare the query
      $query = $writeDB->prepare($queryString);

      // if title has been provided
      if($category_updated === true) {
        // set book object title to given value (checks for valid input)
        $book->setCategory($jsonData->category);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_category = $book->getCategory();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':category', $up_category, PDO::PARAM_STR);
      }

      // if description has been provided
      if($title_updated === true) {
        // set book object description to given value (checks for valid input)
        $book->setTitle($jsonData->title);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_title = $book->getTitle();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':title', $up_title, PDO::PARAM_STR);
      }

      // if deadline has been provided
      if($pagecount_updated === true) {
        // set book object deadline to given value (checks for valid input)
        $book->setPageCount($jsonData->pagecount);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_pagecount = $book->getPageCount();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':pagecount', $up_pagecount, PDO::PARAM_STR);
      }

      // if completed has been provided
      if($language_updated === true) {
        // set book object completed to given value (checks for valid input)
        $book->setLanguage($jsonData->language);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_language= $book->getLanguage();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':language', $up_language, PDO::PARAM_STR);
      }

      // bind the book id provided in the query string
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      // run the query
      $query->execute();

      // get affected row count
      $rowCount = $query->rowCount();

      // check if row was actually updated, could be that the given values are the same as the stored values
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Book not updated - given values may be the same as the stored values");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to return the newly edited book - connect to master database
      $query = $writeDB->prepare('SELECT * from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if book was found
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No Book found");
        $response->send();
        exit;
      }
      // create book array to store returned books
      $bookArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object for each row returned
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create book and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }
      // bundle books and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['books'] = $bookArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Book updated");
      $response->setBookInfo($returnData);
      $response->send();
      exit;
    }
    catch(bookException $ex) {
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
      $response->addMessage("Failed to update Book - check your data for errors");
      $response->send();
      exit;
    }
  }
  elseif($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // attempt to query the database
    try {
      // create db query
      $query = $writeDB->prepare('delete from books where id = :bookid');
      $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Book not found");
        $response->send();
        exit;
      }
      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Book deleted successfully");
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch(PDOException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to delete book");
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
elseif(empty($_GET)) {

  // if request is a GET e.g. get books
  if($_SERVER['REQUEST_METHOD'] === 'GET') {

    // attempt to query the database
    try {
      // ADD AUTH TO QUERY
      // create db query
      $query = $readDB->prepare('SELECT * from books');
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create book array to store returned books
      $bookArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object for each row
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create book and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }

      // bundle books and rows returned into an array to return in the json data
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
      $response->addMessage("Failed to get books");
      $response->send();
      exit;
    }
  }
  // else if request is a POST e.g. create book
  elseif($_SERVER['REQUEST_METHOD'] === 'POST') {

    // create book
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
      // create new book with data, if non mandatory fields not provided then set to null
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

      // get last book id so we can return the book in the json
      $lastBookID = $writeDB->lastInsertId();
      // ADD AUTH TO QUERY
      // create db query to get newly created book - get from master db not read slave as replication may be too slow for successful read
      $query = $writeDB->prepare('SELECT id, category, title, pagecount, language, etag from books where id = :bookid');
      $query->bindParam(':bookid', $lastBookID, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the new book was returned
      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to retrieve book info after creation");
        $response->send();
        exit;
      }

      // create empty array to store books
      $bookArray = array();

      // for each row returned - should be just one
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new book object
        $book = new Book($row['id'], $row['category'], $row['title'], $row['pagecount'], $row['language'], $row['etag']);

        // create book and store in array for return in json data
        $bookArray[] = $book->returnBookAsArray();
      }
      // bundle books and rows returned into an array to return in the json data
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
    // if book fails to create due to data types, missing fields or invalid data then send error json
    catch(BookException $ex) {
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
