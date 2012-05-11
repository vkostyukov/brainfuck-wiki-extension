<?php
// HEADER

if (!defined("MEDIAWIKI")) {
	die("This file is a MediaWiki extension, it is not a valid entry point.");
}

if ( !method_exists("ParserOutput", "addHeadItem") ) {
  die("Sorry, but your MediaWiki version is too old for Brainfuck, please upgrade to the latest MediaWiki version.");
}

$wgExtensionCredits["parserhook"][] = array(
	'name' => "Brainfuck",
	'version' => '0.1',
	'author' => '[mailto:vladimir.kostyukov@intel.com Vladimir Kostyukov]',
	'url' => 'http://vmsotcatom1.fm.intel.com/wiki/',
	'description' => 'Intel/SOTC PERF Extension'
);

if (defined('MW_SUPPORTS_PARSERFIRSTCALLINIT')) {
	$wgHooks['ParserFirstCallInit'][] = "efBrainfuckSetup";
} else {
	$wgExtensionFunctions[] = "efBrainfuckSetup";
}

$wgHooks['LanguageGetMagic'][] = "efBrainfuckMagic";

function efBrainfuckSetup() {
	global $wgParser;
	$hookStub = new BrainfuckHookStub();
	$wgParser->setFunctionHook("brainfuck", array(&$hookStub, "efBrainfuck"));
	$wgParser->setFunctionHook("bf", array(&$hookStub, "efBrainfuck"));
}

function efBrainfuckMagic(&$magicWords, $langCode = "en") {
	$magicWords['brainfuck'] = array( 0, "brainfuck");
	$magicWords['bf'] = array( 0, "bf");
	return true;
}

class Brainfuck {
	public function evaluate($source) {
		return "";
	}
}

class BrainfuckHookStub {
	function efBrainfuck(/*$parser, $code*/) {


	}
}

?>