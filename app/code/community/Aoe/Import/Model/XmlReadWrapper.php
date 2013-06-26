<?php

/**
 * XmlReaderWrapper
 * Extends XMLReader and adds functionality to keep track of paths and other data.
 *
 * @author Fabrizio Branca
 */
class Aoe_Import_Model_XmlReaderWrapper extends XMLReader {

	/**
	 * @var array localNameStack
	 */
	protected $localNameStack = array();

	/**
	 * @var string currentElementName
	 */
	protected $currentElementName = '';

	/**
	 * @var array levelData
	 */
	protected $levelData = array();

	/**
	 * @var string uri
	 */
	protected $uri;

	/**
	 * Wrapper for open method
	 *
	 * @see XMLReader::open()
	 * @param string $URI
	 * @param string $encoding
	 * @param int $options
	 * @return bool Returns true on success or false on failure.
	 */
	public function open($URI, $encoding = null, $options = null) {
		$this->uri = $URI;
		return parent::open($URI, $encoding, $options);
	}

	/**
	 * Get Uri
	 *
	 * @return string
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 * Read method
	 *
	 * @see XMLReader::read()
	 */
	public function read() {
		$result = parent::read();
		if ($this->nodeType == XMLReader::ELEMENT) {
			// detect <empty /> nodes (for which XMLReader::END_ELEMENT doesn't exist)
			while ($this->depth < count($this->localNameStack)) {
				$this->resetLevelData($this->getStackSize()+1);
				array_pop($this->localNameStack);
			}

			$this->currentElementName = $this->localName;
			array_push($this->localNameStack, $this->localName);

			$this->setLevelData('totalSiblingCounter', $this->getLevelData('totalSiblingCounter')+1);
			$this->setLevelData('siblingCounter_'.$this->localName, $this->getLevelData('siblingCounter_'.$this->localName)+1);
		}
		if ($this->nodeType == XMLReader::END_ELEMENT) {
			while ($this->depth < count($this->localNameStack)) {
				$this->resetLevelData($this->getStackSize()+1);
				array_pop($this->localNameStack);
			}
		}

		return $result;
	}

	/**
	 * Resert level data for a given level
	 *
	 * @param int $level
	 * @return void
	 */
	public function resetLevelData($level) {
		unset($this->levelData[$level]);
	}

	/**
	 * Set level data for current level
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function setLevelData($key, $value) {
		$this->levelData[$this->getStackSize()][$key] = $value;
	}

	/**
	 * Get data attached to a level.
	 *
	 * @param string $key
	 * @param int $level
	 * @return mixed
	 */
	public function getLevelData($key, $level=NULL) {
		$requestedLevel = 0;

		if (is_null($level) || $level < 0) {
			$requestedLevel = $this->getStackSize();
		}
		if ($level < 0) {
			$requestedLevel += $level;
		} elseif ($level > 0) {
			$requestedLevel = $level;
		}
		return $this->levelData[$requestedLevel][$key];
	}

	/**
	 * Get sibling count
	 *
	 * @return int
	 */
	public function getSiblingCount() {
		return $this->getLevelData('siblingCounter_'.$this->currentElementName);
	}

	/**
	 * Get local name stack
	 *
	 * @return array
	 */
	public function getLocalNameStack() {
		return $this->localNameStack;
	}

	/**
	 * Get path (xpath-style)
	 * e.g. e.g. //ep/product/name
	 *
	 * @return string
	 */
	public function getPath() {
		return '//'.implode('/', $this->localNameStack);
	}

	/**
	 * Get path with element count (counting all siblings with same name starting at 0)
	 * e.g. //ep[0]/product[712]/name[2]
	 *
	 * @return string
	 */
	public function getPathWithSiblingCount() {
		$pathParts = array();

		$level = 0;
		foreach ($this->localNameStack as $pathSegment) {
			$pathParts[] = $pathSegment . '['. $this->getLevelData('siblingCounter_'.$pathSegment, $level+1) .']';
			$level++;
		}
		return '//'.implode('/', $pathParts);
	}

	/**
	 * Get stack size
	 *
	 * @return int
	 */
	public function getStackSize() {
		return count($this->getLocalNameStack());
	}

}
