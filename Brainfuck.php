<?php
/*
 * Copyright 2012, Brainfuck Wiki Extension
 *
 * Version: 0.2
 * Author: Vladimir Kostyukov <vladimir.kostukov@gmail.com>
 * License: http://www.apache.org/licenses/LICENSE-2.0.html
 *
 */

if (!defined("MEDIAWIKI")) {
	die("This file is a MediaWiki extension, it is not a valid entry point.");
}

if ( !method_exists("ParserOutput", "addHeadItem") ) {
  die("Sorry, but your MediaWiki version is too old for Brainfuck, please upgrade to the latest MediaWiki version.");
}

$wgExtensionCredits["parserhook"][] = array(
	"name" => "Brainfuck",
	"version" => "0.1",
	"author" => "Vladimir Kostyukov",
	"url" => "http://www.mediawiki.org/wiki/Extension:Brainfuck",
	"description" => "Brainfuck Embedded Interpreter"
);

$wgHooks["ParserFirstCallInit"][] = "efBrainfuckSetup";
$wgHooks["LanguageGetMagic"][] = "efBrainfuckMagic";

function efBrainfuckSetup(&$parser) {

	$brainfuck = new Brainfuck();

	$parser->setFunctionHook("brainfuck", array(&$brainfuck, "renderParserFunction"));
	$parser->setFunctionHook("bf", array(&$brainfuck, "renderParserFunction"));

	$parser->setHook("brainfuck", array(&$brainfuck, "renderTag"));
	$parser->setHook("bf", array(&$brainfuck, "renderTag"));

	return true;
}

function efBrainfuckMagic(&$magicWords, $langCode = "en") {

	$magicWords["brainfuck"] = array(0, "brainfuck");
	$magicWords["bf"] = array(0, "bf");

	return true;
}
interface BrainfuckInstruction {
	public function perform(&$context);
}

class PlusInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		if ($GIF) $context["data"][$pointer] = chr(ord($context["data"][$pointer]) + 1);
	}
}

class MinusInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		if ($GIF) $context["data"][$pointer] = chr(ord($context["data"][$pointer]) - 1);
	}
}

class LeftInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];

		if ($GIF && $context["dataPointer"] > 0) $context["dataPointer"]--;
	}
}

class RightInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];

		if ($GIF) {
			$context["dataPointer"]++;
			if (!isset($context["data"][$context["dataPointer"]]))
				$context["data"][$context["dataPointer"]] = chr(0);
		}
	}
}

class LoopInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		$context["NLC"]++;
		if ($GIF) $context["ZF"] = ord($context["data"][$pointer]) == 0;
	}
}

class PoolInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		$context["NLC"]--;
		if ($GIF) $context["ZF"] = ord($context["data"][$pointer]) == 0;
	}
}

class OutInstruction implements BrainfuckInstruction {
	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		if ($GIF) $context["output"] .= $context["data"][$pointer];
	}
}

class InInstruction implements  BrainfuckInstruction {

	private $symbol;

	function __construct($symbol) {
		$this->symbol = $symbol;
	}

	public function perform(&$context) {
		$GIF = $context["GIF"];
		$pointer = $context["dataPointer"];

		if ($GIF) $context["data"][$pointer] = $this->symbol;
	}
}

class BrainfuckParser {
	public function parse($source) {
		$index = 0;
		$code = array();

		while ($index < strlen($source)) {
			switch($source{$index}) {
				case '+': $code[] = new PlusInstruction(); break;
				case '-': $code[] = new MinusInstruction(); break;
				case '<': $code[] = new LeftInstruction(); break;
				case '>': $code[] = new RightInstruction(); break;
				case '.': $code[] = new OutInstruction(); break;
				case ',':
					if ($index + 1 < strlen($source)) $code[] = new InInstruction($source{++$index});
					else $code[] = chr(0);
					break;
				case '[': $code[] = new LoopInstruction(); break;
				case ']': $code[] = new PoolInstruction(); break;
				// TODO: add default err
			}
			$index++;
		}

		return $code;
	}
}

interface BrainfuckInterpreter {
	public function interpret($source);
}

class RecursiveInterpreter implements BrainfuckInterpreter {
	public function interpret($source) {
		$parser = new BrainfuckParser();
		$code = $parser->parse($source);

		$context = array();
		$context["data"] = array(chr(0));
		$context["codePointer"] = 0;
		$context["dataPointer"] = 0;
		$context["GIF"] = true; // Global Interpreter Flag
		$context["ZF"] = true; // Zero Flag
		$context["NLC"] = 0; // Nested Loops Counter
		$context["output"] = "";

		$this->recursiveInterpret($code, $context);

		return $context["output"];
	}

	private function recursiveInterpret($code, &$context) {
		$lif = $context["GIF"]; // Local Interpreter Flag

		for (;$context["codePointer"] < count($code); $context["codePointer"]++) {
			$instruction = $code[$context["codePointer"]];

			$nlcBefore = $context["NLC"];
			$instruction->perform($context);
			$nlcAfter = $context["NLC"];

			if ($nlcAfter > $nlcBefore) {
				if ($context["GIF"] = !$context["ZF"]) {
					$loopPointer = $context["codePointer"]++ - 1;
					$this->recursiveInterpret($code, $context);
					if (!$context["ZF"]) $context["codePointer"] = $loopPointer;
				} else {
					$this->recursiveInterpret($code, $context);
					$context["GIF"] = $lif;
				}
			} elseif ($nlcAfter < $nlcBefore) {
				return;
			}
		}
	}
}

class Brainfuck {

	public function evaluate($code, &$context = array()) {
		$output = ""; $deep = 0;
		$defaults = array("data" => array(chr(0)), "cp" => 0, "dp" => 0);
		$context = array_merge($defaults, $context);

		while (true) {
			switch ($code{$context["cp"]}) {
			case '+':
				$context["data"][$context["dp"]] = chr(ord($context["data"][$context["dp"]]) + 1);
				break;
			case '-':
				$context["data"][$context["dp"]] = chr(ord($context["data"][$context["dp"]]) - 1);
				break;
			case '>':
				$context["dp"]++;
				if (!isset($context["data"][$context["dp"]])) $context["data"][$context["dp"]] = chr(0);
				break;
			case '<':
				if ($context["dp"] == 0) break;
				$context["dp"]--;
				break;
			case '.':
				$output .= $context["data"][$context["dp"]];
				break;
			case ',':
				$context["data"][$context["dp"]] = $context["cp"] == strlen($code) ? chr(0) : $code[$context["cp"]++];
				break;
			case '[':
				if (ord($context["data"][$context["dp"]]) == 0 && $context["dp"] != 0) {
					$deep++;
					while ($deep && $context["cp"]++ < strlen($code)) {
						if ($code[$context["cp"]] == '[') {
							$deep++;
						} elseif ($code[$context["cp"]] == ']') {
							$deep--;
						}
					}
				} else {
					$loop = $context["cp"]++ - 1;
					$output .= self::evaluate($code, $context);
					if (ord($context["data"][$context["dp"]]) != 0) {
						$context["cp"] = $loop;
					}
				}
				break;
			case ']':
				return $output;
			}

			if (++$context["cp"] == strlen($code)) break;
		}

		return $output;
	}

	public function renderParserFunction($parser, $code) {
		return self::evaluate($code);
	}

	public function renderTag($input, $args, $parser) {
		$code = $parser->recursiveTagParse($input);
		return self::evaluate($code);
 	}
}
?>