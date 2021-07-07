<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\xml;

/**
 * Utility class for XML elements
 *
 * @author Felix Heinrichs | Trusted Mails GmbH | <a href="mailto:felix.heinrichs@trusted-mails.com">felix.heinrichs@trusted-mails.com</a>
 * @author Marcus St&auml;nder | Trusted Mails GmbH | <a href="mailto:marcus.staender@trusted-mails.com">marcus.staender@trusted-mails.com</a>
 */
abstract class XMLUtils
{

    /**
     * This function appends one SimpleXMLElement to another one as addChild does not support deep copies
     * @param \SimpleXMLElement $to
     * @param \SimpleXMLElement $from
     */
    static function appendChild(\SimpleXMLElement $to, \SimpleXMLElement $from)
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

   /**
     * Adds a child with $value inside CDATA
     * @param \SimpleXMLElement $parent
     * @param string $name
     * @param string $value
     */
    static public function addChildAsCDATA($parent, $name, $value = NULL)
    {
        $new_child = $parent->addChild($name);

        if ($new_child !== NULL) {
            $node = dom_import_simplexml($new_child);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($value));
        }

        return $new_child;
    }
}
