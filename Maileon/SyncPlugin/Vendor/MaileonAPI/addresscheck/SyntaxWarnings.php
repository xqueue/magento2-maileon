<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck;

/**
 * Class for localizing AddressCheck SyntaxWarnings messages
 */
SyntaxWarnings::init();
class SyntaxWarnings
{
    private static $I18N;

    public static $LANG_HU = "hu_HU";
    public static $LANG_EN = "en_EN";
    public static $LANG_DE = "de_DE";

    public static function getMessage($token, $locale)
    {
        return self::$I18N[$locale][$token];
    }

    public static function init()
    {
        self::$I18N = array(
            self::$LANG_HU => array(
                'synm001' => 'Nincs benne \'@\'.',
                'synm002' => 'Az els&#337; r&eacute;sz nem ismerhet&#337; fel.',
                'synm003' => 'A domain nem ismerhet&#337; fel.',
                'synm004' => 'Az els&#337; r&eacute;sz nem megengedett karaktereket tartalmaz.',
                'synm005' => 'A domain nem megengedett karaktereket tartalmaz.',
                'synm006' => '&Eacute;rv&eacute;nytelen email c&iacute;m szintaxis.',
                'synm007' => '&Eacute;rv&eacute;nytelen els&#337; r&eacute;sz.',
                'synm008' => '&Eacute;rv&eacute;nytelen domain.',
                'synm009' => '&Eacute;rv&eacute;nytelen top-level-domain.',
                'synm010' => 'Az IP-c&iacute;m form&aacute;tuma nem megfelel&#337;.',
                'synm011' => 'Egyn&eacute;l t&ouml;bb \'@\' karaktert tartalmaz.',
                'synm012' => 'A top-level-domain csak bet&#369;kb&#337;l &aacute;llhat, &eacute;s legal&aacute;bb 2 karakter hossz&uacute; kell hogy legyen.',
                'synm013' => 'A postafi&oacute;k neve meghaladja a 64 karakteres max. hossz&uacute;s&aacute;got.',
                'synm014' => 'A domain-n&eacute;v meghaladja a 254 karakteres max. hossz&uacute;s&aacute;got.',
                'synm015' => 'Az email c&iacute;m meghaladja a 254 karakteres max. hossz&uacute;s&aacute;got.',
                'synm017' => 'A domain unicode karaktereket tartalmazott, &eacute;s konvert&aacute;l&aacute;sra ker&uuml;lt. A konvert&aacute;lt v&aacute;ltozat:',
                'synm018' => 'A postafi&oacute;k unicode karaktereket tartalmazott, &eacute;s konvert&aacute;l&aacute;sra ker&uuml;lt. A konvert&aacute;lt v&aacute;ltozat:',
                'extm001' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 32 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm002' => 'Csak a bet&#369;k (a-z) &eacute;s a sz&aacute;mok (0-9) enged&eacute;lyezettek.',
                'extm003' => 'Az els&#337; karakternek bet&#369;nek kell lennie.',
                'extm004' => 'Csak bet&#369;, sz&aacute;m, pont, k&ouml;t&#337;jel &eacute;s alulvon&aacute;s enged&eacute;lyezett.',
                'extm005' => 'Pont, k&ouml;t&#337;jel &eacute;s alulvon&aacute;s nem fordulhat el&#337; t&ouml;bbsz&ouml;r.',
                'extm006' => 'Pont, k&ouml;t&#337;jel &eacute;s alulvon&aacute;s nem fordulhat el&#337; els&#337; vagy utols&oacute; karakterk&eacute;nt.',
                'extm007' => 'Az els&#337; r&eacute;sz v&eacute;g&eacute;n nem lehet pont.',
                'extm008' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 50 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm009' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 40 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm010' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 30 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm011' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 32 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm012' => 'Csak bet&#369;, sz&aacute;m, pont &eacute;s alulvon&aacute;s enged&eacute;lyezett.',
                'extm013' => 'Csak egy pont enged&eacute;lyezett.',
                'extm014' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 6 &eacute;s 25 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm015' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 40 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm016' => 'Pont nem fordulhat el&#337; t&ouml;bbsz&ouml;r.',
                'extm017' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 2 &eacute;s 30 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm018' => 'Csak a bet&#369;, sz&aacute;m, plusz-, m&iacute;nusz-, and-jel, pont, k&ouml;t&#337;jel &eacute;s alulvon&aacute;s enged&eacute;lyezett.',
                'extm019' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 6 &eacute;s 30 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm020' => 'Csak bet&#369;, sz&aacute;m &eacute;s pont enged&eacute;lyezett.',
                'extm021' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 3 &eacute;s 20 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm022' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 1 &eacute;s 64 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm023' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 6 &eacute;s 20 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm024' => 'Az els&#337; r&eacute;sz hossza legal&aacute;bb 2 karakter legyen.',
                'extm025' => 'Az els&#337; r&eacute;sz hossz&aacute;nak 1 &eacute;s 16 karakter k&ouml;z&ouml;tt kell lennie.',
                'extm026' => 'Csak bet&#369; vagy sz&aacute;m szerepelhet els&#337; karakterk&eacute;nt.',
                'extm027' => 'A k&uuml;l&ouml;nleges karakterek nem enged&eacute;lyezettek az els&#337; r&eacute;sz elej&eacute;n &eacute;s v&eacute;g&eacute;n.',
                'extm028' => 'Alulvon&aacute;s nem fordulhat el&#337; t&ouml;bbsz&ouml;r.',
                'extm029' => 'Nem szerepelhet pont el&#337;l vagy h&aacute;tul.',
                'extm030' => 'Az els&#337; r&eacute;sz els&#337; karakter&eacute;nek bet&#369;nek vagy sz&aacute;mnak kell lennie.'
            ),
            self::$LANG_EN => array(
                'synm001' => '\'@\' missing',
                'synm002' => 'Local part not recognized',
                'synm003' => 'Domain not recognized',
                'synm004' => 'Local part contains invalid characters (Umlaut)',
                'synm005' => 'Domain contains invalid characters (Umlaut)',
                'synm006' => 'invalid Email address syntax',
                'synm007' => 'invalid local part',
                'synm009' => 'top level domain unknown',
                'synm008' => 'invalid domain',
                'synm010' => 'IP address improperly formatted',
                'synm011' => '\'@\' existing more than once',
                'synm012' => 'Top level domain may consist of alphabetic characters only, with a minimum length of 2 characters',
                'synm013' => 'Inbox name exceeds maximum length of 64 characters',
                'synm014' => 'Domain name exceeds maximum length of 254 characters',
                'synm015' => 'Email address exceeds maximum length of 254 characters',
                'extm001' => 'Local part length must be between 3 and 16 characters',
                'extm002' => 'Only alphabetic (a-z) and numeric characters (0-9) permitted',
                'extm003' => 'Starting character must be alphabetic',
                'extm004' => 'Only alphabetic and numeric characters, dots, hyphens and underscores permitted',
                'extm005' => 'Only one dot, hyphen or underscore permitted',
                'extm006' => 'Dot, hyphen or underscore not permitted as starting or ending character',
                'extm007' => 'No dot permitted as local part ending character',
                'extm008' => 'Local part length must be between 3 and 50 characters',
                'extm009' => 'Local part length must be between 3 and 40 characters',
                'extm010' => 'Local part length must be between 3 and 30 characters',
                'extm011' => 'Local part length must be between 3 and 32 characters',
                'extm012' => 'Only alphabetic and numeric characters, dots and underscores permitted',
                'extm013' => 'Only one dot permitted',
                'extm015' => 'Local part length must be between 3 and 40 characters',
                'extm014' => 'Local part length must be between 6 and 25 characters',
                'extm016' => 'Multiple dots not permitted',
                'extm017' => 'Local part length must be between 2 and 30 characters',
                'extm018' => 'Only alphabetic and numeric characters, plus, minus, ampersand (&), dots, hyphens and underscores permitted',
                'extm019' => 'Local part length must be between 6 and 30 characters',
                'extm020' => 'Only alphabetic and numeric characters and dots permitted',
                'extm021' => 'Local part length must be between 3 and 20 characters',
                'extm022' => 'Local part length must be between 1 and 64 characters',
                'extm023' => 'Local part length must be between 6 and 20 characters',
                'extm024' => 'Local part minimum length must be 2 characters',
                'extm025' => 'Local part length must be between 1 and 16 characters',
                'extm027' => 'No special characters permitted as local part starting or ending character',
                'extm026' => 'Only alphabetic and numeric characters permitted as starting character'
            ),
            self::$LANG_DE => array(
                'synm001' => '\'@\' nicht vorhanden',
                'synm002' => 'Local-Part nicht erkannt',
                'synm003' => 'Domain nicht erkannt',
                'synm004' => 'Local-Part enth&auml;lt Umlaute',
                'synm005' => 'Domain enth&auml;lt Umlaute',
                'synm006' => 'ung&uuml;ltiges E-Mail-Adress-Syntax',
                'synm007' => 'ung&uuml;ltiger Local-Part',
                'synm008' => 'ung&uuml;ltige Domain',
                'synm009' => 'unbekannte Top-Level-Domain',
                'synm010' => 'IP-Adresse falsch formatiert',
                'synm011' => '\'@\' mehrmals vorhanden.',
                'synm012' => 'Top-Level-Domain darf lediglich aus Buchstaben bestehen bei einer Mindestl&auml;nge von 2 Zeichen',
                'synm013' => 'Postfachname &uuml;berschreitet Maximall&auml;nge von 64 Zeichen',
                'synm014' => 'Domain-Name &uuml;berschreitet Maximall&auml;nge von 254 Zeichen',
                'synm015' => 'E-Mail-Adresse &uuml;berschreitet Maximall&auml;nge von 254 Zeichen',
                'synm017' => 'Die Domain enthielt Unicode-Zeichen und wurde konvertiert. Die konvertierte Form:',
                'synm018' => 'Die Mailbox enthielt Unicode-Zeichen und wurde konvertiert. Die konvertierte Form:',
                'extm001' => 'Local-Part muss L&auml;nge zwischen 3-32 Zeichen aufweisen',
                'extm002' => 'Lediglich Buchstaben (a-z) und Ziffern (0-9) erlaubt',
                'extm003' => 'Beginnendes Zeichen muss ein Buchstabe sein',
                'extm004' => 'Lediglich Buchstaben, Ziffern, Punkt, Binde- und Unterstrich erlaubt',
                'extm005' => 'Punkt, Binde- und Unterstrich d&uuml;rfen nicht mehrfach auftreten',
                'extm006' => 'Punkt, Binde- und Unterstrich d&uuml;rfen nicht am Anfang oder Ende vorkommen',
                'extm007' => 'Punkte Ende des Local-Parts nicht erlaubt',
                'extm008' => 'Local-Part muss L&auml;nge zwischen 3-50 Zeichen aufweisen',
                'extm009' => 'Local-Part muss L&auml;nge zwischen 3-40 Zeichen aufweisen',
                'extm010' => 'Local-Part muss L&auml;nge zwischen 3-30 Zeichen aufweisen',
                'extm011' => 'Local-Part muss L&auml;nge zwischen 3-32 Zeichen aufweisen',
                'extm012' => 'Lediglich Buchstaben, Ziffern, Punkte und Unterstriche erlaubt',
                'extm013' => 'Lediglich ein Punkt erlaubt',
                'extm014' => 'Local-Part muss L&auml;nge zwischen 6-25 Zeichen aufweisen',
                'extm015' => 'Local-Part muss L&auml;nge zwischen 3-40 Zeichen aufweisen',
                'extm016' => 'Punkte d&uuml;rfen nicht mehrfach auftreten.',
                'extm017' => 'Local-Part muss L&auml;nge zwischen 2-30 Zeichen aufweisen',
                'extm018' => 'Lediglich Buchstaben, Ziffern, Plus, Minus, Schr&auml;gstrich, k&auml;ufm&auml;nnisches Und, Punkte, Binde- und Unterstriche erlaubt',
                'extm019' => 'Local-Part muss L&auml;nge zwischen 6-30 Zeichen aufweisen',
                'extm020' => 'Lediglich Buchstaben, Ziffern und Punkte erlaubt',
                'extm021' => 'Local-Part muss L&auml;nge zwischen 3-20 Zeichen aufweisen',
                'extm022' => 'Local-Part muss L&auml;nge zwischen 1-64 Zeichen aufweisen',
                'extm023' => 'Local-Part muss L&auml;nge zwischen 6-20 Zeichen aufweisen',
                'extm024' => 'Local-Part muss Mindestl&auml;nge von 2 Zeichen aufweisen',
                'extm025' => 'Local-Part muss L&auml;nge zwischen 1-16 Zeichen aufweisen',
                'extm026' => 'Lediglich Buchstaben oder Ziffern als erstes Zeichen erlaubt',
                'extm027' => 'Sonderzeichen am Anfang und Ende des Local-Parts nicht erlaubt',
                'extm028' => 'Unterstriche d&uuml;rfen nicht mehrfach auftreten',
                'extm029' => 'Punkte d&uuml;rfen nicht am Anfang oder Ende vorkommen',
                'extm030' => 'Das erste Zeichen des Local-Parts muss ein Buchstabe oder eine Zahl sein'
            )
        );
    }
}
?>
