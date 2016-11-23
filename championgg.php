<?php
class ChampionGG {
	private $manaless = array("Aatrox", "DrMundo", "Mordekaiser", "Vladimir", "Zac", "Akali", "Kennen", "LeeSin", "Shen",
								"Zed", "Garen", "Gnar", "Katarina", "RekSai", "Renekton", "Rengar", "Riven", "Rumble",
								"Shyvana", "Tryndamere", "Yasuo");
	
	public function getAllSets() {
		echo "Creating item sets for all champions..." . "<br>";
		$time = time();
		$saveFolder = $time . "_ItemSets";
		$page = $this->getPage("http://champion.gg/");
		preg_match_all('/<a href="([^"]*)" style="display:block">/si', $page, $list);
		foreach ($list[1] as $champPage) {
			$data = explode("/", $champPage);
			$champ = $data[2];
			$role = $data[3];

			$this->getOneSet($champ, $role, $saveFolder, $time);
		}
		echo "Complete!\n";
		return true;
	}

	public function getOneSet($champ, $role, $saveFolder = null, $time = null) {
		if ($time == null) {
			$time = time();
		}
		$url = "http://champion.gg/champion/" . $champ . "/" . $role;

		$page = $this->getPage($url);
				$data = $this->getBetween($page, "matchupData.championData = ", "matchupData.patchHistory");
		$data = trim($data);
		$data = trim($data, ";");
		$champJSON = json_decode($data, true);
		$currentPatch = $this->getBetween(str_replace(array("\n", " "), "", $page), "<small>Patch<strong>", "</strong>");

		$firstMG = $champJSON["firstItems"]["mostGames"];
		$firstHWP = $champJSON["firstItems"]["highestWinPercent"];
		$fullMG = $champJSON["items"]["mostGames"];
		$fullHWP = $champJSON["items"]["highestWinPercent"];

		$skillsMG = $champJSON["skills"]["mostGames"];
		$skillsHWP = $champJSON["skills"]["highestWinPercent"];

		

		if (!isset($firstMG["games"], $firstHWP["games"], $fullMG["games"], $fullHWP["games"])) {
			echo "Woops, full data is unavailable for " . $champ . " in " . $role . " role" . "<br>";
			file_put_contents($time . "_Unavailable.txt", "* " . $champ . " - " . $role . " role" . "<br>", FILE_APPEND);
			return false;
		}		

		$consumeItems = array(2003, 2004, 2043, 2031, 2032, 2033, 2138, 2140, 2139);
		$trinketItems = array(3340, 3341, 2055);
		
		if (in_array($champ, $this->manaless)) {
			unset($consumeItems[1]);
		}
		
		$skillsItems = array(3364, 3363, 2003);

		$firstMGItems = array_merge($this->getItems($firstMG), $this->getItems($trinketItems, true));
		$firstHWPItems = array_merge($this->getItems($firstHWP), $this->getItems($trinketItems, true));
		$fullMGItems = $this->getItems($fullMG);
		$fullHWPItems = $this->getItems($fullHWP);
		
		$firstMGBlock = array(
			"items" => $firstMGItems,
			"type" => "Most Frequent Starters (" . $firstMG["winPercent"] . "% win - " . $firstMG["games"] . " games)"
		);
		$firstHWPBlock = array(
			"items" => $firstHWPItems,
			"type" => "Highest Win Rate Starters (" . $firstHWP["winPercent"] . "% win - " . $firstHWP["games"] . " games)"
		);
		$fullMGBlock = array(
			"items" => $fullMGItems,
			"type" => "Most Frequent Build (" . $fullMG["winPercent"] . "% win - " . $fullMG["games"] . " games)"
		);
		$fullHWPBlock = array(
			"items" => $fullHWPItems,
			"type" => "Highest Win Rate Build (" . $fullHWP["winPercent"] . "% win - " . $fullHWP["games"] . " games)"
		);
		$consumeBlock = array(
			"items" => $this->getItems($consumeItems, true),
			"type" => "Consumables"
		);			
		
		$skillsMGOrder = $this->getSkills($skillsMG);
		$skillsHWPOrder = $this->getSkills($skillsHWP);
		
		$skillsMGBlock = array(
			"items" => $this->getItems($skillsItems, true),
			"type" => $skillsMGOrder . " (" . $skillsMG["winPercent"] . "% win - " . $skillsMG["games"] . " games)"
		);
		$skillsHWPBlock = array(
			"items" => $this->getItems($skillsItems, true),
			"type" => $skillsHWPOrder . " (" . $skillsHWP["winPercent"] . "% win - " . $skillsHWP["games"] . " games)"
		);

		$roleFormatted = substr($champJSON["role"], 0, 1) . substr(strtolower($champJSON["role"]), 1);
		if ($roleFormatted == "Duo_carry") {
			$roleFormatted = "ADC";
		}
		else if ($roleFormatted == "Duo_support") {
			$roleFormatted = "Support";
		}
		
		$itemSetArr = array(
			"map" => "any",
			"isGlobalForChampions" => false,
			"blocks" => array(
				$firstMGBlock,
				$firstHWPBlock,
				$fullMGBlock,
				$fullHWPBlock,
				$consumeBlock,
				$skillsMGBlock,
				$skillsHWPBlock
			),
			"associatedChampions" => array(),
			"title" => $roleFormatted . " " . $currentPatch,
			"priority" => false,
			"mode" => "any",
			"isGlobalForMaps" => true,
			"associatedMaps" => array(),
			"type" => "custom",
			"sortrank" => 1,
			"champion" => $champJSON["key"]
		);
		
		if ($skillsMGOrder == $skillsHWPOrder) {
			array_pop($itemSetArr["blocks"]);
		}

		if ($firstMGItems == $firstHWPItems) {
			unset($itemSetArr["blocks"][1]);
		}
		
		if ($fullMGItems == $fullHWPItems) {
			unset($itemSetArr["blocks"][3]);
		}
		
		$itemSetArr["blocks"] = array_values($itemSetArr["blocks"]);
		
		if ($saveFolder == null) {
			$saveFolder = $champJSON["key"] . "/Recommended";
		}
		else {
			$saveFolder = $saveFolder . "/" . $champJSON["key"] . "/Recommended";
		}
		
		if (!file_exists($saveFolder)) {
			mkdir($saveFolder, 0777, true);
		}
		$fileName = str_replace(".", "_", $currentPatch) . "_" . $roleFormatted . ".json";
		$fileName = $saveFolder . "/" . $fileName;
		$itemSetJSON = json_encode($itemSetArr, JSON_PRETTY_PRINT);
		file_put_contents($fileName, $itemSetJSON);
		echo "Saved set for <strong>" . $champ . "</strong> in " . $role . " role <br>";
		return true;
	}

	private function getSkills($array) {
		$skillStr = "";

		foreach ($array["order"] as $index => $skill) {
			$level = $index + 1;
			$skill = strtr($skill, array(
				"1" => "Q",
				"2" => "W",
				"3" => "E",
				"4" => "R"
			));

			if ($level == 6 || $level == 12) {
				$skill .= " > ";
			}
			
			$skillStr .= $skill;			
		}
		return $skillStr;
	}

	private function getItems($array, $fromPreset = false) {
		$items = array();
		if ($fromPreset) {
			foreach ($array as $item) {
				$items[] = array(
					"count" => 1,
					"id" => (string) $item
				);
			}
		}
		else {
			$itemIDs = array();
			foreach ($array["items"] as $item) {
				$id = $item["id"];
				if ($id == 2009 || $id == 2010) {
					$id = 2003;
				}
				if (isset($itemIDs[$id])) {
					$itemIDs[$id]++;
				}
				else {
					$itemIDs[$id] = 1;
				}
			}

			foreach ($itemIDs as $itemID => $count) {
				$items[] = array(
					"count" => $count,
					"id" => (string) $itemID
				);
			}
		}
		return $items;
	}

	private function getBetween($content, $start, $end){
		$r = explode($start, $content);
		if (isset($r[1])) {
			$r = explode($end, $r[1]);
			return $r[0];
		}
		return '';
	}

	private function getPage($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.3.18 (KHTML, like Gecko) Version/8.0.3 Safari/600.3.18");
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$data = curl_exec($ch);
		return $data;
	}
}

$champ = new ChampionGG();
//$champ->getOneSet("Bard", "Support");
$champ->getAllSets();
?>
