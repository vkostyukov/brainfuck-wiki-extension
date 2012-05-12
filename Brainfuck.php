<?php
/*
 * Copyright 2012, Brainfuck Wiki Extension
 *
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
	"description" => "Brainfuck Wiki Extension"
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