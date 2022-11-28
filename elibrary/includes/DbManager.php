<?php
/**
 * DbManager.php
 * 
 * Class that manages interactions with database.
 */
class DbManager
{
	private $DB_HOST;
	private $DB_NAME;
	private $DB_USER;
	private $DB_PASS;

	private $_Connection = null;
	private function Connection()
	{
		/* activate MySQLi reporting */
		$driver = new mysqli_driver();
		$driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

		if($this->_Connection == null)
		{
			try
			{
				$this->_Connection = new mysqli($this->DB_HOST, $this->DB_USER, $this->DB_PASS, $this->DB_NAME);
				$this->_Connection->set_charset("utf8");
			}catch(mysqli_sql_exception $e)
			{
				throw new DbException("C'&egrave stato un problema con la connessione al database.", DbException::ERR_CONNECTION, $e);
			}
		}
		return $this->_Connection;
	}

	public function __construct()
	{
		if(UNIPD_DELIVER)
		{
			$this->DB_HOST = "localhost";
			$this->DB_NAME = "asalviat"; // TODO
			$this->DB_USER = "asalviat";
			$this->DB_PASS = "iexaezain4Reb8Lo";
		}else
		{
			$this->DB_HOST = "localhost";
			$this->DB_NAME = "wgbdflgo_elibrary";
			$this->DB_USER = "wgbdflgo_elibrary";
			$this->DB_PASS = "elibrary2022";
		}

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		// Force the connection initialization
		$this->Connection();
	}

	public function categorySave(Category $category)
	{
		if($category->getId() == null)
			$this->categoryInsert($category);
		else
			$this->categoryUpdate($category);
	}

	public function categoryInsert(Category $category)
	{
		// Check if username or email already exists
		if($category == null)
			return false;

		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"INSERT INTO categories (" . 
					"IDParentCategory, " .
					"Name" .
				") VALUES (?, ?)"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("is", $category->getIdParentCategory(), $category->getName());
			$stmt->execute();
			$user->setId($this->Connection()->insert_id);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function categoryUpdate(Category $category)
	{
		// Check if username or email already exists
		if($category == null)
			return false;

		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"UPDATE categories SET " . 
					"IDParentCategory = ?, " .
					"Name = ? " .
				"WHERE IDCategory = ?"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("isi", $category->getIdParentCategory(), $category->getName(), $category->getId());
			$stmt->execute();
			$user->setId($this->Connection()->insert_id);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function categoriesTree($idcategoryparent = null)
	{
		$categories = array();
		$stmt = null;
		try
		{
			if($idcategoryparent != null)
			{
				$stmt = $this->Connection()->prepare("SELECT * FROM categories WHERE IDParentCategory = ? ORDER BY Name");
				$stmt->bind_param("i", $idcategoryparent);
			}else
				$stmt = $this->Connection()->prepare("SELECT * FROM categories WHERE IDParentCategory IS NULL ORDER BY Name");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			if($result->num_rows > 0)
			{
				while($catassoc = $result->fetch_assoc())
				{
					$category = new Category();
					$category
						->setId($catassoc["IDCategory"])
						->setIdParentCategory($catassoc["IDParentCategory"])
						->setName($catassoc["Name"]);
					$categories[] = $category;
				}
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		foreach($categories as $category)
			if($category->getIdParentCategory() != null)
			{
				$category->setChilds($this->categoriesTree($category->getId()));
			}
		return $categories;
	}

	public function userSave(User $user)
	{
		if($user->getId() == null)
			$this->userInsert($user);
		else
			$this->userUpdate($user);
	}

	public function userInsert(User $user)
	{
		// Check if username or email already exists
		if($user == null || $this->checkUsernameExists($user->getUsername()) || $this->checkUserEmailExists($user->getEmail()))
			return false;

		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"INSERT INTO users (" . 
					"IDUserType," . 
					"Username," .
					"Email," .
					"Name," .
					"Surname," . 
					"BirthDate," . 
					"Password," . 
					"AdditionalInfo," .
					"F_Privacy," .
					"F_Marketing," .
					"TsCreate" .
				") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}

		$dn = $user->getBirthDate();
		
		try
		{
			@$stmt->bind_param("isssssssii", $user->getType(), $user->getUsername(), $user->getEmail(), $user->getName(), $user->getSurname(), $dn->format("Y-m-d"), $user->getPassword(), $user->getAdditionalInfo(), $user->getPrivacy(), $user->getMarketing());
			$stmt->execute();
			$user->setId($this->Connection()->insert_id);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function userUpdate(User $user)
	{
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"UPDATE users SET " .
					"IDUserType = ?," .
					"Name = ?," .
					"Surname = ?," . 
					"BirthDate = ?," .
					"Password = ?," . 
					"AdditionalInfo = ?," .
					"F_Privacy = ?," .
					"F_Marketing = ?, " .
					"TsUpdate = NOW() " . 
				"WHERE IDUser = ?"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("isssssiis", $user->getType(), $user->getName(), $user->getSurname(), $user->getBirthDate()->format("Y-m-d"), $user->getPassword(), $user->getAdditionalInfo(), $user->getPrivacy(), $user->getMarketing(), $user->getId());
			$stmt->execute();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function userUpdateLastLogin(User $user)
	{
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"UPDATE users SET TsLastLogin = NOW() " . 
				"WHERE IDUser = ?"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("i", $user->getId());
			$stmt->execute();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function checkUsernameExists($username)
	{
		$num_rows = 1;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM users WHERE Username = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("s", $username);
			$stmt->execute();
			$result = $stmt->get_result();
			$num_rows = $result->num_rows;
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $num_rows > 0;
	}

	public function checkUserEmailExists($email)
	{
		$num_rows = 1;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM users WHERE Email = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("s", $email);
			$stmt->execute();
			$result = $stmt->get_result();
			$num_rows = $result->num_rows;
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $num_rows > 0;
	}

	public function getUserByLogin($username, $password)
	{
		$user = null;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM users WHERE Username = ? OR Email = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("ss", $username, $username);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result->num_rows > 0)
			{
				$userassoc = $result->fetch_assoc();
				if(password_verify($password, $userassoc["Password"]))
				{
					$datanascita = DateTime::createFromFormat("Y-m-d", $userassoc["BirthDate"]);
					if ($datanascita === false)
						$datanascita = null;
					$tscreate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsCreate"]);
					if ($tscreate === false)
						$tscreate = null;
					$tsupdate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsUpdate"]);
					if ($tsupdate === false)
						$tsupdate = null;
					$tslastlogin = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsLastLogin"]);
					if ($tslastlogin === false)
						$tslastlogin = null;

					$user = new User();
					$user
						->setId($userassoc["IDUser"])
						->setType($userassoc["IDUserType"])
						->setUsername($userassoc["Username"])
						->setEmail($userassoc["Email"])
						->setPassword($userassoc["Password"])
						->setName($userassoc["Name"])
						->setSurname($userassoc["Surname"])
						->setBirthDate($datanascita)
						->setAdditionalInfo($userassoc["AdditionalInfo"])
						->setPrivacy($userassoc["F_Privacy"])
						->setMarketing($userassoc["F_Marketing"])
						->setTsCreate($tscreate)
						->setTsUpdate($tsupdate)
						->setTsLastLogin($tslastlogin);
				}
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $user;
	}

	public function getBooksByAuthor($idauthor)
	{
		$books = array();
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM books NATURAL JOIN books_authors WHERE IDAuthor = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("i", $idauthor);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result->num_rows > 0)
			{
				while($bookassoc = $result->fetch_assoc())
				{
					$book = new Book();
					$book
						->setId($bookassoc["IDBook"])
						->setTitle($bookassoc["Title"])
						->setPubYear($bookassoc["PubYear"])
						->setEditor($bookassoc["Editor"])
						->setPrice($bookassoc["Price"])
						->setRatingValue($bookassoc["RatingValue"])
						->setRatingCount($bookassoc["RatingCount"])
						->setSoldQuantity($bookassoc["SoldQuantity"])
						->setShortDescription($bookassoc["ShortDescription"])
						->setDescription($bookassoc["Description"]);
					$books[] = $book;
				}
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $books;
	}

	public function bookSave(Book $book)
	{
		if($book->getId() == null)
			$this->bookInser($book);
		else
			$this->bookUpdate($book);
	}

	public function bookInsert(Book $book)
	{
		// Check if username or email already exists
		if($book == null)
			return false;

		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"INSERT INTO books (" . 
					"Title," .
					"PubYear," .
					"Editor," .
					"Price," . 
					"ShortDescription," . 
					"Description," .
					"TsCreate" . 
				") VALUES (?, ?, ?, ?, ?, ?, NOW())"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("sisfss", $book->getTitle(), $book->getPubYear(), $book->getEditor(), $book->getPrice(), $book->getShortDescription(), $book->getDescription());
			$stmt->execute();
			$user->setId($this->Connection()->insert_id);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function bookUpdate(Book $book)
	{
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"UPDATE Auto SET " .
					"Title = ?," .
					"PubYear = ?," . 
					"Editor = ?," . 
					"Price = ?," .
					"ShortDescription = ?," .
					"Description = ?," .
					"TsUpdate = NOW() " .
				"WHERE IDBook = ?"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("sisfss", $book->getTitle(), $book->getPubYear(), $book->getEditor(), $book->getPrice(), $book->getShortDescription(), $book->getDescription());
			$stmt->execute();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function getUserById($id)
	{
		$id = intval($id);
		if($id == 0)
			return null;

		$user = null;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM users WHERE IDUser = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result->num_rows > 0)
			{
				$userassoc = $result->fetch_assoc();
				$datanascita = DateTime::createFromFormat("Y-m-d", $userassoc["BirthDate"]);
				if ($datanascita === false)
					$datanascita = null;
				$tscreate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsCreate"]);
				if ($tscreate === false)
					$tscreate = null;
				$tsupdate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsUpdate"]);
				if ($tsupdate === false)
					$tsupdate = null;
				$tslastlogin = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsLastLogin"]);
				if ($tslastlogin === false)
					$tslastlogin = null;

				$user = new User();
				$user
					->setId($userassoc["ID"])
					->setType($userassoc["IDUserType"])
					->setUsername($userassoc["Username"])
					->setEmail($userassoc["Email"])
					->setPassword($userassoc["Password"])
					->setName($userassoc["Name"])
					->setSurname($userassoc["Surname"])
					->setBirthDate($datanascita)
					->setAdditionalInfo($userassoc["AdditionalInfo"])
					->setPrivacy($userassoc["F_Privacy"])
					->setMarketing($userassoc["F_Marketing"])
					->setTsCreate($tscreate)
					->setTsUpdate($tsupdate)
					->setTsLastLogin($tslastlogin);
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $user;
	}

	public function getUsers()
	{
		$users = array();
		
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM users ORDER BY Surname, Name");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			while ($userassoc = $result->fetch_assoc()) 
			{
				$userassoc = $result->fetch_assoc();
				$datanascita = DateTime::createFromFormat("Y-m-d", $userassoc["BirthDate"]);
				if ($datanascita === false)
					$datanascita = null;
				$tscreate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsCreate"]);
				if ($tscreate === false)
					$tscreate = null;
				$tsupdate = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsUpdate"]);
				if ($tsupdate === false)
					$tsupdate = null;
				$tslastlogin = DateTime::createFromFormat("Y-m-d H:i:s", $userassoc["TsLastLogin"]);
				if ($tslastlogin === false)
					$tslastlogin = null;

				$user = new User();
				$user
					->setId($userassoc["ID"])
					->setType($userassoc["IDUserType"])
					->setUsername($userassoc["Username"])
					->setEmail($userassoc["Email"])
					->setPassword($userassoc["Password"])
					->setName($userassoc["Name"])
					->setSurname($userassoc["Surname"])
					->setBirthDate($datanascita)
					->setAdditionalInfo($userassoc["AdditionalInfo"])
					->setPrivacy($userassoc["F_Privacy"])
					->setMarketing($userassoc["F_Marketing"])
					->setTsCreate($tscreate)
					->setTsUpdate($tsupdate)
					->setTsLastLogin($tslastlogin);
				$users[] = $user;
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement ".__FUNCTION__." ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $users;
	}

/*
	public function saveEvento(Evento $evento)
	{
		if($evento->getId() == null)
			$this->insertEvento($evento);
		else
			$this->updateEvento($evento);
	}

	public function insertEvento(Evento $evento)
	{
		// Check if username or email already exists
		if($evento == null)
			return false;

		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"INSERT INTO Raduno (" . 
					"Nome," .
					"Data," .
					"Luogo," .
					"Descrizione," . 
					"QuotaIscrizione," .
					"IDOrganizzatore," . 
					"PhotoPath" .
				") VALUES (?, ?, ?, ?, ?, ?, ?)"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement insertEvento ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("ssssiis", $evento->getNome(), $evento->getData()->format("Y-m-d H:i"), $evento->getLuogo(), $evento->getDescrizione(), $evento->getQuotaIscrizione(), $evento->getIdOrganizzatore(), $evento->getPhotoPath());
			$stmt->execute();
			$evento->setId($this->Connection()->insert_id);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement insertEvento ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function updateEvento(Evento $evento)
	{
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare(
				"UPDATE Raduno SET " .
					"Nome = ?," .
					"Data = ?," . 
					"Luogo = ?," . 
					"Descrizione = ?," .
					"QuotaIscrizione = ?," .
					"IDOrganizzatore = ?," .
					"PhotoPath = ? " .
				"WHERE ID = ?"
			);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement updateEvento ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}
		
		try
		{
			@$stmt->bind_param("ssssiisi", $evento->getNome(), $evento->getData()->format("Y-m-d H:i"), $evento->getLuogo(), $evento->getDescrizione(), $evento->getQuotaIscrizione(), $evento->getIdOrganizzatore(), $evento->getPhotoPath(), $evento->getId());
			$stmt->execute();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement updateEvento ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
	}

	public function getEventById($id)
	{
		$evento = null;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM Raduno WHERE ID = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getEventById ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result->num_rows > 0)
			{
				$row = $result->fetch_assoc();
				$data = DateTime::createFromFormat("Y-m-d", $row["Data"]);
				if ($data === false)
					$data = null;

				$evento = new Evento();
				$evento
					->setId($row["ID"])
					->setNome($row["Nome"])
					->setData($data)
					->setLuogo($row["Luogo"])
					->setDescrizione($row["Descrizione"])
					->setQuotaIscrizione($row["QuotaIscrizione"])
					->setIdOrganizzatore($row["IDOrganizzatore"])
					->setPhotoPath($row["PhotoPath"])
					->setOrganizzatore($this->getUserById($row["IDOrganizzatore"]));
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getEventById ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $evento;
	}

	public function deleteEventById($id)
	{
		$evento = null;
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("DELETE FROM Raduno WHERE ID = ?");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement deleteEventById ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->bind_param("i", $id);
			$result = $stmt->execute();
			if($result === false)
			{
				throw new DbException("Il prepared statement deleteEventById ha fallito l'execute: " . htmlspecialchars($this->Connection()->error), DbException::ERR_QUERY, null);
			}
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement deleteEventById ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}catch(DbException $e)
		{
			throw $e;
		}finally
		{
			$stmt->close();
		}
		return $evento;
	}

	public function getEvents()
	{
		$eventi = array();
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM Raduno ORDER BY Data DESC");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getEvents ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) 
			{
				$data = DateTime::createFromFormat("Y-m-d", $row["Data"]);
				if ($data === false)
					$data = null;

				$evento = new Evento();
				$evento
					->setId($row["ID"])
					->setNome($row["Nome"])
					->setData($data)
					->setLuogo($row["Luogo"])
					->setDescrizione($row["Descrizione"])
					->setQuotaIscrizione($row["QuotaIscrizione"])
					->setIdOrganizzatore($row["IDOrganizzatore"])
					->setPhotoPath($row["PhotoPath"])
					->setOrganizzatore($this->getUserById($row["IDOrganizzatore"]));
				$eventi[] = $evento;
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCarByUser ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $eventi;
	}

	public function getFutureEvents()
	{
		$eventi = array();
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM Raduno WHERE Data >= NOW() ORDER BY Data ASC");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getEvents ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) 
			{
				$data = DateTime::createFromFormat("Y-m-d", $row["Data"]);
				if ($data === false)
					$data = null;

				$evento = new Evento();
				$evento
					->setId($row["ID"])
					->setNome($row["Nome"])
					->setData($data)
					->setLuogo($row["Luogo"])
					->setDescrizione($row["Descrizione"])
					->setQuotaIscrizione($row["QuotaIscrizione"])
					->setIdOrganizzatore($row["IDOrganizzatore"])
					->setPhotoPath($row["PhotoPath"])
					->setOrganizzatore($this->getUserById($row["IDOrganizzatore"]));
				$eventi[] = $evento;
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCarByUser ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $eventi;
	}

	public function getCars()
	{
		$books = array();
		
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM Auto");
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCars ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			while ($bookassoc = $result->fetch_assoc()) 
			{
				$book = new CarProfile();
				$book
					->setId($bookassoc["ID"])
					->setMarca($bookassoc["Marca"])
					->setModello($bookassoc["Modello"])
					->setAnno($bookassoc["Anno"])
					->setTarga($bookassoc["Targa"])
					->setDescrizione($bookassoc["Descrizione"])
					->setColore($bookassoc["Colore"])
					->setAlimentazione($bookassoc["Alimentazione"])
					->setCilindrata($bookassoc["Cilindrata"])
					->setStorico($bookassoc["Storico"])
					->setPhotoPath($bookassoc["PhotoPath"])
					->setIdUtente($bookassoc["IDUtente"]);
				$owner = $this->getUserById($book->getIdUtente());
				$book->setOwner($owner);
				$books[] = $book;
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCars ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $books;
	}

	public function getLastCars($nr_cars = 10)
	{
		$books = array();
		
		$stmt = null;
		try
		{
			$stmt = $this->Connection()->prepare("SELECT * FROM Auto ORDER BY TsCreate DESC LIMIT 0," . $nr_cars);
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCars ha fallito la creazione: " . htmlspecialchars($this->Connection()->error), DbException::ERR_PREPSTMT, $e);
		}		
		try
		{
			$stmt->execute();
			$result = $stmt->get_result();
			while ($bookassoc = $result->fetch_assoc()) 
			{
				$book = new CarProfile();
				$book
					->setId($bookassoc["ID"])
					->setMarca($bookassoc["Marca"])
					->setModello($bookassoc["Modello"])
					->setAnno($bookassoc["Anno"])
					->setTarga($bookassoc["Targa"])
					->setDescrizione($bookassoc["Descrizione"])
					->setColore($bookassoc["Colore"])
					->setAlimentazione($bookassoc["Alimentazione"])
					->setCilindrata($bookassoc["Cilindrata"])
					->setStorico($bookassoc["Storico"])
					->setPhotoPath($bookassoc["PhotoPath"])
					->setIdUtente($bookassoc["IDUtente"]);
				$owner = $this->getUserById($book->getIdUtente());
				$book->setOwner($owner);
				$books[] = $book;
			}
			$result->close();
		}catch(mysqli_sql_exception $e)
		{
			throw new DbException("Il prepared statement getCars ha fallito l'execute: " . htmlspecialchars($stmt->error), DbException::ERR_QUERY, $e);
		}finally
		{
			$stmt->close();
		}
		return $books;
	}*/
}