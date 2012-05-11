<?php
// HEADER

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
	"description" => "Brainfuck Interpreter"
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
	$magicWords["brainfuck"] = array( 0, "brainfuck");
	$magicWords["bf"] = array( 0, "bf");
	return true;
}

class Brainfuck {
	public function evaluate($code) {
		return $code;
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