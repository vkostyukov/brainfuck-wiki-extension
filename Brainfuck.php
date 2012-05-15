<?php
/*
 * Copyright 2012, Brainfuck Wiki Extension
 *
 * Version: 0.3
 * Author: Vladimir Kostyukov <vladimir.kostukov@gmail.com>
 * License: http://www.apache.org/licenses/LICENSE-2.0.html
 *
 */

if (!defined("MEDIAWIKI")) {
	die("This file is a MediaWiki extension, it is not a valid entry point.");
}

if (!method_exists("ParserOutput", "addHeadItem")) {
  die("Sorry, but your MediaWiki version is too old for Brainfuck, please upgrade to the latest MediaWiki version.");
}

$wgExtensionCredits["parserhook"][] = array(
	"name" => "Brainfuck",
	"version" => "0.3",
	"author" => "Vladimir Kostyukov",
	"url" => "http://www.mediawiki.org/wiki/Extension:Brainfuck",
	"description" => "Brainfuck Embedded Interpreter"
);

if (defined('MW_SUPPORTS_PARSERFIRSTCALLINIT')) {
	$wgHooks["ParserFirstCallInit"][] = "efBrainfuckSetup";
} else {
	$wgExtensionFunctions[] = "efBrainfuckSetup";
}

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

class ParseException extends Exception { }
class InterpretException extends Exception { }

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

class InInstruction implements BrainfuckInstruction {

	private $symbol;

	public function __construct($symbol) {
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
					else $code[] = new InInstruction(chr(0));
					break;
				case '[': $code[] = new LoopInstruction(); break;
				case ']': $code[] = new PoolInstruction(); break;
				case '\n':
				case ' ':
				case '\t':
				case '\r': break;
				default: throw new ParseException("parse error: unexpected symbol \"".$source{$index}."\"");
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

	public static $MAX_NESTED_LOOPS = 3000;

	private $parser;

	public function __construct() {
		$this->parser = new BrainfuckParser();
	}

	public function interpret($source) {
		try {
			$code = $this->parser->parse($source);

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
		} catch (ParseException $pe) {
			return $pe->getMessage();
		} catch (InterpretException $ie) {
			return $ie->getMessage();
		}
	}

	private function recursiveInterpret($code, &$context) {
		$lif = $context["GIF"]; // Local Interpreter Flag

		for (;$context["codePointer"] < count($code); $context["codePointer"]++) {
			$instruction = $code[$context["codePointer"]];

			$nlcBefore = $context["NLC"];
			$instruction->perform($context);
			$nlcAfter = $context["NLC"];

			if ($nlcAfter > self::$MAX_NESTED_LOOPS) {
				throw new InterpretException("interpret exception: overflow nested loops counter: ".$nlcAfter." loops");
			}

			if ($nlcAfter < 0) {
				throw new InterpretException("interpret exception: broken loop statement");
			}

			if ($nlcAfter > $nlcBefore) {
				if ($context["GIF"] = !$context["ZF"]) {
					$loopPointer = $context["codePointer"]++ - 1;
					$this->recursiveInterpret($code, $context);
					if (!$context["ZF"]) $context["codePointer"] = $loopPointer;
				} else {
					$context["codePointer"]++;
					$this->recursiveInterpret($code, $context);
					$context["GIF"] = $lif;
				}
			} elseif ($nlcAfter < $nlcBefore) {
				return;
			}
		}

		if ($context["NLC"] != 0) {
			throw new InterpretException("interpret exception: broken loop statement");
		}
	}
}

class Brainfuck {

	public function evaluate($source) {
		$interpreter = new RecursiveInterpreter();
		return $interpreter->interpret($source);
	}

	public function renderParserFunction($parser, $source) {
		return $this->evaluate($source);
	}

	public function renderTag($input, $args, $parser) {
		$source = $parser->recursiveTagParse($input);
		return $this->evaluate($source);
 	}
}
?>