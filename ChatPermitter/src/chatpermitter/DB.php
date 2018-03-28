<?php

namespace chatpermitter;

class DB
{
	public function __construct(string $dir)
	{
		$this->db = new \SQLite3($dir . "user.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS userdata (
			name PRIMARY KEY,
			date
		)");
	}


	public function setDate(string $defaultName)
	{
		$name = strtolower($defaultName);
		$value = "INSERT INTO userdata (name, date) VALUES (:name, :date)";
		$db = $this->db->prepare($value);

		$db->bindValue(":name", $name);
		$db->bindValue(":date", time());

		$db->execute();
	}


	public function getDate(string $defaultName)
	{
		$name = strtolower($defaultname);
		$value = "SELECT date FROM userdata WHERE name = :name";
		$db = $this->db->prepare($value);

		$db->bindValue(":name", $name);

		$result = $db->execute()->fetchArray(SQLITE3_ASSOC);

		return empty($result) ? null : $result;		
	}


	public function isDate(string $defaultName)
	{
		$name = strtolower($defaultName);
		$value = 'SELECT name FROM userdata WHERE name = :name';
		$db = $this->db->prepare($value);

		$db->bindValue(":name", $name);

		$result = $db->execute()->fetchArray(SQLITE3_ASSOC);

		return empty($result) ? false : true;
	}


	public function rmDate(string $defaultName)
	{
		$name = strtolower($defaultName);
		$value = 'DELETE FROM userdata WHERE name = :name';
		$db = $this->db->prepare($value);

		$db->bindValue(":name", $name);

		$db->execute();
	}
}