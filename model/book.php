<?php
// Task Model Object

// empty TaskException class so we can catch task errors
class BookException extends Exception { }

class Book {
	// define private variables
	// define variable to store book id number
	private $_id;
	// define variable to store book category
	private $_category;
	// define variable to store book title
	private $_title;
	// define variable to store book page count
	private $_pagecount;
	// define variable to store book language
	private $_language;
	// define variable to store book etag
	private $_etag;


  // constructor to create the book object with the instance variables already set
	public function __construct($id, $category, $title, $pagecount, $language, $etag) {
		$this->setID($id);
		$this->setCategory($category);
		$this->setTitle($title);
		$this->setPageCount($pagecount);
		$this->setLanguage($language);
		$this->setEtag($etag);
	}

  // function to return book ID
	public function getID() {
		return $this->_id;
	}

	// function to return book category
	public function getCategory() {
		return $this->_category;
	}

  // function to return book title
	public function getTitle() {
		return $this->_title;
	}

  // function to return page count
	public function getPageCount() {
		return $this->_pagecount;
	}

  // function to return book langugage
	public function getLanguage() {
		return $this->_language;
	}

  // function to return book etag
	public function getEtag() {
		return $this->_etag;
	}

	// function to set the private book ID
	public function setID($id) {
		// if passed in book ID is not null or not numeric, is not between 0 and 9223372036854775807 (signed bigint max val - 64bit)
		// over nine quintillion rows
		if(($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
			throw new BookException("Book ID error");
		}
		$this->_id = $id;
	}

	// function to set the private Book Category
	public function setCategory($category) {
		// if passed in title is not between 1 and 255 characters
		if(strlen($category) < 1 || strlen($category) > 255) {
			throw new BookException("Book Category error");
		}
		$this->_category = $category;
	}

  // function to set the private book title
	public function setTitle($title) {
		// if passed in title is not between 1 and 255 characters
		if(strlen($title) < 1 || strlen($title) > 255) {
			throw new BookException("Book title error");
		}
		$this->_title = $title;
	}

  // function to set the private task description
	public function setPageCount($pagecount) {
		// if passed in description is not null and is either 0 chars or is greater than 16777215 characters (mysql mediumtext size), can be null but not empty
		if(($pagecount === null)) {
			throw new BookException("book page count error");
		}
		$this->_pagecount = $pagecount;
	}

  // public function to set the private task deadline date and time
	public function setLanguage($language) {
		// make sure the value is null OR if not null validate date and time passed in, must create date time ok and still match the same string passed (e.g. prevent 31/02/2018)
		if(strlen($language) < 1 || strlen($language) > 20) {
			throw new BookException("Book language error");
	  }
	  $this->_language = $language;
	}

	// function to set the private task completed
	public function setEtag($etag) {
		// Set the etag variable
		if(strlen($etag) !== 10) {
			throw new BookException("Etag error");
		}
		$this->_etag = strtoupper($etag);
	}


  // function to return task object as an array for json
	public function returnBookAsArray() {
		$task = array();
		$task['id'] = $this->getID();
		$task['category'] = $this->getCategory();
		$task['title'] = $this->getTitle();
		$task['pagecount'] = $this->getPageCount();
		$task['language'] = $this->getLanguage();
		$task['etag'] = $this->getEtag();
		return $task;
	}

}
