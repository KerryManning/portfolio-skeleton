<?php

class ProjectData {

	private $db;
	private $sql;

	public function setDb($db) {
		$this->db = $db;
	}

	public function query($query) {
		$this->sql = $this->db->prepare($query);
	}

	public function bind($param, $value, $type = null) {
		if(is_null($type)) {
			switch(true) {
				case is_int($value):
					$type = PDO::PARAM_INT;
					break;
				case is_bool($value):
					$type = PDO::PARAM_BOOL;
					break;
				case is_null($value):
					$type = PDO::PARAM_NULL;
					break;
				default:
					$type = PDO::PARAM_STR;
			}
		}
		$this->sql->bindValue($param, $value, $type);
	}

	public function execute() {
		try {
			return $this->sql->execute();
			echo "executed";
		} catch (Exception $e) {
			echo "<p>Unable to retrieve results from database.</p>";
			exit;
		}
	}

	public function thumbnail_array_results() {
		$this->execute();
		while ($thumbs = $this->sql->fetch(PDO::FETCH_ASSOC)) {
			$thumbnails[$thumbs["title"]] ["thumb_image"][$thumbs["thumb"]] = $thumbs["thumb_alt"];
		}
		return $thumbnails;	
	}

	public function project_array_results() {
		$this->execute();
		while($data = $this->sql->fetch(PDO::FETCH_ASSOC)) {
			$project["title"] = $data["title"];
			$project["description"] = $data["description"];
			$project['body'] = explode("/ ", $data['body']);
			$project['images'] = array_combine(explode(", ", $data['img']), explode(", ", $data['img_alt']));
			$project["tech_tags"] = explode(", ", $data["tech_tags"]);
			$project["website"] = $data["website"];
		}
		return $project;
	}

}


