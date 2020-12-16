<?php

require __DIR__ . '/vendor/autoload.php';

require('auth.php');


if ($_POST['document_url']) {
	$doc = new DocParser($_POST['document_url']);

	$result = $doc->generate();

	$filename = $_POST['name'] ? slug($_POST['name']) : slug($doc->doc->getTitle());


	if (!isset($_POST['preview'])) {
		header('Content-Description: File Transfer');
		header('Content-Type: text/x-php');
		header('Content-Disposition: attachment; filename="' . $filename . '.html.php"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . strlen($result));
		header('Content-Transfer-Encoding: binary');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	}

	echo $result;
	exit();
}

function slug($url)
{
	$url = strtolower(str_replace(array(" ", "&"), array("-", "-and-"), $url));
	$url = str_replace(array("(", ")", ".", "@", "#", "$", "%", "¨", "*", "{", "[", "}", "]", "\"", "'", "=", "+", "§", "ª", "º", ",", "/", "\\", "~", "^"), "", $url);

	while (strpos($url, "--") !== FALSE) $url = str_replace("--", "-", str_replace("--", "-", $url));
	if (substr($url, -1) == "-") $url = substr($url, 0, -1);

	$url = strip_accents($url);
	$url = urlencode(preg_replace("/[^A-Za-z0-9\-]/", "", $url));

	return $url;
}
function strip_accents($string)
{
	return strtr($string, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

class DocParser
{

	public function __construct($url)
	{
		$this->client = getClient();
		$this->service = new Google_Service_Docs($this->client);

		$this->document_id = str_replace("https://", "", $url);
		$this->document_id = explode("/", $this->document_id);
		$this->document_id = $this->document_id[3];

		$this->doc = $this->service->documents->get($this->document_id);

		$this->getTemplate();
	}

	public function getTemplate()
	{
		$html = new DOMDocument();
		@$html->loadHTML(file_get_contents('template.html'));
		$html->formatOutput = true;

		$this->html = $html;
	}

	public function generate()
	{

		$html = $this->html;
		$doc = $this->doc;

		$title = $html->getElementsByTagName('title')->item(0);
		$body = $html->getElementsByTagName('body')->item(0);
		$this->content = $body->getElementsByTagName('top-content')->item(0);

		$title->nodeValue = $doc->getTitle();

		foreach ($doc->getBody()->getContent() as $structuralElement) {
			$simpleElement = $structuralElement->toSimpleObject();

			$this->createElement($simpleElement);
		}



		// echo "<textarea style='width: 100%; height: 100%'>";
		return $html->saveHTML();
		// echo "</textarea>";

	}

	function createElement($simpleElement, $root=null) {

		if (!$root) $root = &$this->content;
		
		// Handle title
		if (
			isset($simpleElement->paragraph->paragraphStyle->namedStyleType)
			&& strpos($simpleElement->paragraph->paragraphStyle->namedStyleType, 'HEADING_') === 0
		) {
			$element = $this->getTitleElement($simpleElement->paragraph, $root);

			// Handle image
		} elseif (
			isset($simpleElement->paragraph->elements[0]->inlineObjectElement)
		) {
			$element =  $this->getImageElement($simpleElement->paragraph, $root, $this->doc->getInlineObjects());

			// handle paragraph
		} elseif (isset($simpleElement->paragraph)) {
			$element =  $this->getParagraphElement($simpleElement->paragraph, $root);

			// handle table
		} elseif (isset($simpleElement->table)) {
			$element =  $this->getTableElement($simpleElement->table, $root);
		} 

		return $element;
	}


	function getTitleElement($gdocElement, $root)
	{

		$gdocsStyleType = $gdocElement->paragraphStyle->namedStyleType;
		$headingLevel = explode('HEADING_', $gdocsStyleType);
		$headingLevel = array_pop($headingLevel);

		$content = trim($gdocElement->elements[0]->textRun->content);

		$titleElement = $this->html->createElement('h' . $headingLevel, $content);

		$root->appendChild($titleElement);
	}

	function getImageElement($gdocElement, $root, $inlineObjects)
	{

		$imageId = $gdocElement->elements[0]->inlineObjectElement->inlineObjectId;
		$imageUrl = $inlineObjects[$imageId]->getInlineObjectProperties()->getEmbeddedObject()->getImageProperties()->getContentUri();

		$embedElement = $this->html->createElement('img');
		$embedElement->setAttribute('src', $imageUrl);


		$root->appendChild($embedElement);
	}

	function getParagraphElement($gdocElement, $root)
	{

		if (isset($gdocElement->bullet)) {
			$level = $gdocElement->bullet->nestingLevel;
			if (!$level) $level = 0;

			if (!$this->list[$gdocElement->bullet->listId][$level]) {
				$this->list[$gdocElement->bullet->listId][$level] = $this->html->createElement('ul');
				$root->appendChild($this->list[$gdocElement->bullet->listId][$level]);
			}
			
			if (!isset($this->list[$gdocElement->bullet->listId][$level - 1])) {
				$this->list[$gdocElement->bullet->listId][$level - 1] = $this->html->createElement('ul');
				$root->appendChild($this->list[$gdocElement->bullet->listId][$level - 1]);
			}
			$this->list[$gdocElement->bullet->listId][$level - 1]->appendChild($this->list[$gdocElement->bullet->listId][$level]);
			

			$paragraphElement  = $this->html->createElement('li');
			$this->list[$gdocElement->bullet->listId][$level]->appendChild($paragraphElement);
		}
		else {
			$paragraphElement = $this->html->createElement('p');
		}

		foreach ($gdocElement->elements as $element) {

			$content = trim($element->textRun->content);
			if (!$content) continue;
			$content = $this->html->createTextNode(" $content ");

			$node = $b = $em = null;

			if (isset($element->textRun->textStyle->link)) {
				$node = $this->html->createElement('a');
				$node->setAttribute('href', $element->textRun->textStyle->link->url);
			} 
			if (isset($element->textRun->textStyle->bold)) {
				$b = $this->html->createElement('b');
				if ($node) $node->appendChild($b);
				else $node = $b;
			}
			if (isset($element->textRun->textStyle->italic)) {
				$em = $this->html->createElement('em');
				if ($b) $b->appendChild($em);
				elseif ($node) $node->appendChild($em);
				else $node = $em;
			}

			if ($em) $em->appendChild($content);
			elseif ($b) $b->appendChild($content);
			elseif ($node) $node->appendChild($content);
			else $node = $content;


			$paragraphElement->appendChild($node);
		}

		if (!isset($gdocElement->bullet)) $root->appendChild($paragraphElement);
	}

	function getTableElement($gdocElement, $root)
	{

		$tableElement = $this->html->createElement('table');
		$tableElement->setAttribute('border', 1);

		$tableBodyElement = $this->html->createElement('tbody');


		foreach ($gdocElement->tableRows as $tableRow) {

			$rowElement = $this->html->createElement('tr');

			foreach ($tableRow->tableCells as $tableCell) {
				$cellContent = trim($tableCell->content[0]->paragraph->elements[0]->textRun->content);
				$cellPara = $this->html->createElement('p', $cellContent);

				$cellElement = $this->html->createElement('td');

				if ($tableCell->tableCellStyle->columnSpan > 1) $cellElement->setAttribute('colspan', $tableCell->tableCellStyle->columnSpan);

				foreach ($tableCell->content as $content) {
					$this->createElement($content, $cellElement);
				}

				$rowElement->appendChild($cellElement);
			}

			$tableBodyElement->appendChild($rowElement);
		}

		$tableElement->appendChild($tableBodyElement);

		$root->appendChild($tableElement);
	}
}
