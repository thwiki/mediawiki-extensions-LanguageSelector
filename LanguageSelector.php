<?php
/**
 * LanguageSelector extension - Adds a language selector on every page
 *
 * Features:
 *  * Automatic detection of the language to use for anonymous visitors
 *  * Adds selector for preferred language to every page (also works for anons)
 *
 * This extension may be combined with the Polyglot and the MultiLang extension
 * to provide more internationalization support.
 *
 * @link https://www.mediawiki.org/wiki/Extension:LanguageSelector Documentation
 *
 * @file LanguageSelector.php
 * @ingroup Extensions
 * @package MediaWiki
 * @author Daniel Kinzler (Duesentrieb), brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'Language Selector',
	'author'         => 'Daniel Kinzler',
	'url'            => 'https://mediawiki.org/wiki/Extension:LanguageSelector',
	'descriptionmsg' => 'languageselector-desc',
);

define( 'LANGUAGE_SELECTOR_USE_CONTENT_LANG',    0 ); # no detection
define( 'LANGUAGE_SELECTOR_PREFER_CONTENT_LANG', 1 ); # use content language if accepted by the client
define( 'LANGUAGE_SELECTOR_PREFER_CLIENT_LANG',  2 ); # use language most preferred by the client

/**
* Language detection mode for anonymous visitors.
* Possible values:
* * LANGUAGE_SELECTOR_USE_CONTENT_LANG - use the $wgLanguageCode setting (default content language)
* * LANGUAGE_SELECTOR_PREFER_CONTENT_LANG - use the $wgLanguageCode setting, if accepted by the client
* * LANGUAGE_SELECTOR_PREFER_CLIENT_LANG - use the client's preferred language, if in $wgLanguageSelectorLanguages
*/
$wgLanguageSelectorDetectLanguage = LANGUAGE_SELECTOR_PREFER_CLIENT_LANG;

/**
* Languages to offer in the language selector. Per default, this includes all languages MediaWiki knows
* about by virtue of languages/Names.php. A shorter list may be more usable, though.
*/
$wgLanguageSelectorLanguages = null;

/**
* Determine if language codes are shown in the selector, in addition to names;
*/
$wgLanguageSelectorShowCode = false;

/**
 * Show all languages defined, not only those with a language file.
 */
$wgLanguageSelectorShowAll = false;

define( 'LANGUAGE_SELECTOR_MANUAL',    0 ); # don't place anywhere
define( 'LANGUAGE_SELECTOR_AT_TOP_OF_TEXT', 1 ); # put at the top of page content
define( 'LANGUAGE_SELECTOR_IN_TOOLBOX',  2 ); # put into toolbox
define( 'LANGUAGE_SELECTOR_AS_PORTLET', 3 ); # as portlet
define( 'LANGUAGE_SELECTOR_INTO_SITENOTICE', 11 ); # put after sitenotice text
define( 'LANGUAGE_SELECTOR_INTO_TITLE', 12 ); # put after title text
define( 'LANGUAGE_SELECTOR_INTO_SUBTITLE', 13 ); # put after subtitle text
define( 'LANGUAGE_SELECTOR_INTO_CATLINKS', 14 ); # put after catlinks text

$wgLanguageSelectorLocation = LANGUAGE_SELECTOR_AT_TOP_OF_TEXT;

// register hook handlers
$wgHooks['AddNewAccount'][] = 'wfLanguageSelectorAddNewAccount';
$wgHooks['GetCacheVaryCookies'][] = 'wfLanguageSelectorGetCacheVaryCookies';
$wgHooks['ParserFirstCallInit'][] = 'wfLanguageSelectorSetHook';
$wgHooks['UserGetLanguageObject'][] = 'wfLanguageSelectorGetLanguageCode';
$wgHooks['MenuSidebarAfterBuild'][] = 'wfLanguageSelectorSidebar';

$wgExtensionFunctions[] = 'wfLanguageSelectorExtension';

$wgParserOutputHooks['languageselector'] = 'wfLanguageSelectorAddJavascript';

$wgResourceModules['ext.languageSelector'] = array(
	'scripts' => 'LanguageSelector.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'LanguageSelector'
);

$dir = __DIR__ . '/';
$wgMessagesDirs['LanguageSelector'] = __DIR__ . '/i18n';
$wgJSAutoloadClasses['LanguageSelector'] = 'extensions/LanguageSelector/LanguageSelector.js';

/**
 * @param  $parser Parser
 * @return bool
 */
function wfLanguageSelectorSetHook( $parser ) {
	$parser->setHook( 'languageselector', 'wfLanguageSelectorTag' );
	return true;
}

function wfLanguageSelectorExtension() {
	// We'll probably be beaten to this by the call in wfLanguageSelectorGetLanguageCode(),
	// but just in case, call this to make sure the global is properly initialised
	wfGetLanguageSelectorLanguages();
}

function wfGetLanguageSelectorLanguages() {
	global $wgLanguageSelectorLanguages, $wgLanguageSelectorShowAll;
	if ( $wgLanguageSelectorLanguages === null ) {
		$wgLanguageSelectorLanguages = array_keys( Language::fetchLanguageNames(
			null,
			$wgLanguageSelectorShowAll === true ? 'mw': 'mwfile'
		) );
		sort( $wgLanguageSelectorLanguages );
	}
	return $wgLanguageSelectorLanguages;
}

/**
 * Hook to UserGetLanguageObject
 * @param  $user User
 * @param  $code String
 * @return bool
 */
function wfLanguageSelectorGetLanguageCode( $user, &$code ) {
	global $wgLanguageSelectorDetectLanguage,
		$wgCommandLineMode, $wgRequest, $wgContLang;

	if ( $wgCommandLineMode ) {
		return true;
	}

	$setlang = $wgRequest->getVal( 'setlang' );
	if ( $setlang && !in_array( $setlang, wfGetLanguageSelectorLanguages() ) ) {
		$setlang = null; // ignore invalid
	}

	if ( $setlang ) {
		$wgRequest->response()->setcookie( 'LanguageSelectorLanguage', $setlang );
		$requestedLanguage = $setlang;
	} else {
		$requestedLanguage = $wgRequest->getCookie( 'LanguageSelectorLanguage' );
	}

	if ( $setlang && !$user->isAnon() ) {
		if ( $setlang != $user->getOption( 'language' ) ) {
			$user->setOption( 'language', $requestedLanguage );
			$user->saveSettings();
			$code = $requestedLanguage;
		}
	}

	if ( !$wgRequest->getVal( 'uselang' ) && $user->isAnon() ) {
		if ( $wgLanguageSelectorDetectLanguage != LANGUAGE_SELECTOR_USE_CONTENT_LANG ) {
			if ( $requestedLanguage ) {
				$code = $requestedLanguage;
			} else {
				$languages = $wgRequest->getAcceptLang();

				// see if the content language is accepted by the client.
				if ( $wgLanguageSelectorDetectLanguage != LANGUAGE_SELECTOR_PREFER_CONTENT_LANG
					|| !array_key_exists( $wgContLang->getCode(), $languages ) )
				{

					$supported = wfGetLanguageSelectorLanguages();
					// look for a language that is acceptable to the client
					// and known to the wiki.
					foreach ( $languages as $reqCode => $q ) {
						if ( in_array( $reqCode, $supported ) ) {
							$code = $reqCode;
							break;
						}
					}

					// Apparently Safari sends stupid things like "de-de" only.
					// Try again with stripped codes.
					foreach ( $languages as $reqCode => $q ) {
						$stupidPHP = explode( '-', $reqCode, 2 );
						$bareCode = array_shift( $stupidPHP );
						if ( in_array( $bareCode, $supported ) ) {
							$code = $bareCode;
							break;
						}
					}
				}
			}
		}
	}

	return true;
}

function wfLanguageSelectorGetCacheVaryCookies( $out, &$cookies ) {
	global $wgCookiePrefix;
	$cookies[] = $wgCookiePrefix . 'LanguageSelectorLanguage';
	return true;
}

/**
 * @param  $input String
 * @param  $args Array
 * @param  $parser Parser
 * @return string
 */
function wfLanguageSelectorTag( $input, $args, $parser ) {
	$style = @$args['style'];
	$class = @$args['class'];
	$selectorstyle = @$args['selectorstyle'];
	$buttonstyle = @$args['buttonstyle'];
	$showcode = @$args['showcode'];

	if ( $style ) {
		$style = htmlspecialchars( $style );
	}
	if ( $class ) {
		$class = htmlspecialchars( $class );
	}
	if ( $selectorstyle ) {
		$selectorstyle = htmlspecialchars( $selectorstyle );
	}
	if ( $buttonstyle ) {
		$buttonstyle = htmlspecialchars( $buttonstyle );
	}

	if ( $showcode ) {
		$showcode = strtolower( $showcode );
		if ( $showcode == "true" || $showcode == "yes" || $showcode == "on" ) {
			$showcode = true;
		} elseif ( $showcode == "false" || $showcode == "no" || $showcode == "off" ) {
			$showcode = false;
		} else {
			$showcode = null;
		}
	} else {
		$showcode = null;
	}

	# So that this also works with parser cache
	$parser->getOutput()->addOutputHook( 'languageselector' );

	return wfLanguageSelectorHTML( $parser->getTitle(), $style, $class, $selectorstyle, $buttonstyle, $showcode );
}

function wfLanguageSelectorSidebar( $skin, &$bar ) {
	$lines = [];
	$code = $skin->getLanguage()->getCode();
	foreach ( wfGetLanguageSelectorLanguages() as $ln ) {
		$lines[] = array(
			$href = $skin->getTitle()->getFullURL( 'setlang=' . $ln ),
			'text' => Language::fetchLanguageName( $ln ),
			'href' => $href,
			'id' => 'n-languageselector-' . $ln,
			'active' => ( $code === $ln ),
		);
	}

	$bar['languageselector'] = $lines;

	return true;
}

/**
 * @param  $u User
 * @return bool
 */
function wfLanguageSelectorAddNewAccount( $u ) {
	global $wgUser, $wgLang;

	// inherit language;
	// if $wgUser is the created user this means remembering what the user selected
	// otherwise, it would mean inheriting the language from the user creating the account.
	if ( $wgUser === $u ) {
		$u->setOption( 'language', $wgLang->getCode() );
		$u->saveSettings();
	}

	return true;
}

/**
 * @param  $outputPage OutputPage
 * @param  $parserOutput ParserOutput
 * @param  $data
 * @return void
 */
function wfLanguageSelectorAddJavascript( $outputPage, $parserOutput, $data ) {
	$outputPage->addModules( 'ext.languageSelector' );
}

function wfLanguageSelectorHTML( Title $title, $style = null, $class = null, $selectorstyle = null, $buttonstyle = null, $showCode = null ) {
	global $wgLang, $wgScript, $wgLanguageSelectorShowCode;

	if ( $showCode === null ) {
		$showCode = $wgLanguageSelectorShowCode;
	}

	static $id = 0;
	$id += 1;

	$code = $wgLang->getCode();

	$html = '';
	$html .= Xml::openElement( 'span', array(
		'id' => 'languageselector-box-' . $id,
		'class' => 'languageselector ' . $class,
		'style' => $style
	) );
	$html .= Xml::openElement( 'form', array(
		'name' => 'languageselector-form-' . $id,
		'id' => 'languageselector-form-' . $id,
		'method' => 'get',
		'action' => $wgScript,
		'style' => 'display:inline;'
	) );
	$html .= Html::Hidden( 'title', $title->getPrefixedDBKey() );
	$html .= Xml::openElement( 'select', array(
		'name' => 'setlang',
		'id' => 'languageselector-select-' . $id,
		'style' => $selectorstyle
	) );

	foreach ( wfGetLanguageSelectorLanguages() as $ln ) {
		$name = Language::fetchLanguageName( $ln );
		if ( $showCode ) $name = LanguageCode::bcp47( $ln ) . ' - ' . $name;

		$html .= Xml::option( $name, $ln, $ln == $code );
	}

	$html .= Xml::closeElement( 'select' );
	$html .= Xml::submitButton( wfMessage( 'languageselector-setlang' )->text(),
		array( 'id' => 'languageselector-commit-' . $id, 'style' => $buttonstyle ) );
	$html .= Xml::closeElement( 'form' );
	$html .= Xml::closeElement( 'span' );

	return $html;
}
