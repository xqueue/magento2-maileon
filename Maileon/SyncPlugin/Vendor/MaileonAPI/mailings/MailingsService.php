<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\mailings;

use Maileon\SyncPlugin\Vendor\MaileonAPI\AbstractMaileonService;

/**
 * Facade that wraps the REST service for mailings.
 *
 * @author Marcus St&auml;nder | Trusted Mails GmbH | <a href="mailto:marcus.staender@trusted-mails.com">marcus.staender@trusted-mails.com</a>
 * @author Andreas Lange | XQueue GmbH | <a href="mailto:andreas.lange@xqueue.com">andreas.lange@xqueue.com</a>
 */
class MailingsService extends AbstractMaileonService
{
    /**
     * Sets the template for a mailing
     * @param int $mailingId
     *  the id of the mailing
     * @param string $templateId
     *  the id of the template
     * @return \MaileonAPIResult
     */
    function setTemplate($mailingId, $templateId)
    {
        return $this->post('mailings/' . $mailingId . '/template/' . rawurlencode($templateId), "");
    }

    /**
     * Sets the payload for a mailing by id
     * @param int $mailingId
     *  the mailing id
     * @param string $payload
     *  the payload xml to post
     * @return \MaileonAPIResult
     */
    function setXMLPayload($mailingId, $payload)
    {
        return $this->post('mailings/' . $mailingId . '/payload', $payload, array());
    }

    /**
     * Creates a new mailing.
     * @param string $name
     *  the name of the mailing
     * @param string $subject
     *  the subject of the mailing
     * @param bool $deprecatedParameter
     *  this parameter was never used by the API
     * @param string $type
     *  the type of the mailing, which can be one of 'doi', 'trigger', or 'regular'.
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function createMailing($name, $subject, $deprecatedParameter = false, $type = "regular")
    {
        $queryParameters = array(
            'name' => urlencode($name),
            'subject' => urlencode($subject),
            'type' => urlencode($type),
        );

        return $this->post('mailings', "", $queryParameters);
    }

    /**
     * Disable all QoS checks for a given mailing
     */
    function disableQosChecks($mailingId)
    {
        return $this->put('mailings/' . $mailingId . '/settings/disableQosChecks');
    }

    /**
     * Sets the dispatch logic for trigger mailings
     */
    function setTriggerDispatchLogic($mailingId, $logic)
    {
        $queryParameters = array();
        return $this->put('mailings/' . $mailingId . '/dispatching', $logic, $queryParameters);
    }

    /**
     * Used for DOI Mailings
     * */
    function setTriggerActive($mailingId)
    {
        return $this->post('mailings/' . $mailingId . '/dispatching/activate', "");
    }

    /**
     * Deletes an active trigger mailing.
     * @param integer $id
     *  the ID of the mailing to delete
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function deleteActiveTriggerMailing($mailingId)
    {
        return $this->delete("mailings/".$mailingId."/dispatching");
    }

    /**
     * Deletes a mailing by ID.
     * @param integer $id
     *  the ID of the mailing to delete
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function deleteMailing($id)
    {
        return $this->delete("mailings/".$id);
    }

    /**
     * Updates the HTML content of the mailing referenced by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $html
     *  the new HTML content of the mailing
     * @param bool $doImageGrabbing
     *  specifies if image grabbing should be performed
     * @param bool $doLinkTracking
     *  specifies if link tracking should be performed
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setHTMLContent($mailingId, $html, $doImageGrabbing = true, $doLinkTracking = false)
    {
        $queryParameters = array(
            'doImageGrabbing' => ($doImageGrabbing == TRUE) ? "true" : "false",
            'doLinkTracking' => ($doLinkTracking == TRUE) ? "true" : "false"
        );
        return $this->post('mailings/' . $mailingId . '/contents/html', $html, $queryParameters, "text/html");
    }

    /**
     * Updates the TEXT content of the mailing referenced by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $text
     *  the new TEXT content of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setTextContent($mailingId, $text)
    {
        return $this->post('mailings/' . $mailingId . '/contents/text', $text, array(), "text/plain");
    }

    /**
     * Fetches the HTML content of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the HTML content string of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getHTMLContent($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/contents/html', null, "text/html");
    }

    /**
     * Fetches the TEXT content of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the TEXT content string of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getTextContent($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/contents/text', null, "text/html");
    }

    /**
     * Updates the target group id of the mailing referenced by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $targetGroupId
     *  the ID of the target group to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setTargetGroupId($mailingId, $targetGroupId)
    {
        return $this->post('mailings/' . $mailingId . '/targetgroupid', "<targetgroupid>" . $targetGroupId . "</targetgroupid>");
    }

    /**
     * Fetches the target group id of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the target group id of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getTargetGroupId($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/targetgroupid', null);
    }

    /**
     * Updates the sender email address of the mailing referenced by the given ID. <br />
     * Note: if not only the local part but also the domain is provided, make sure that is exists in Maileon.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $email
     *  the ID of the target group to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setSender($mailingId, $email)
    {
        return $this->post('mailings/' . $mailingId . '/contents/sender', "<sender>" . $email . "</sender>");
    }

    /**
     * Fetches the sender email address of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the sender email address of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getSender($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/contents/sender');
    }

    /**
     * Updates the subject of the mailing referenced by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $subject
     *  the subject of the mailing to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setSubject($mailingId, $subject)
    {
        return $this->post('mailings/' . $mailingId . '/contents/subject', "<subject>" . $subject . "</subject>");
    }

    /**
     * Fetches the subject of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the subject of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getSubject($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/contents/subject');
    }

    /**
     * Updates the senderalias of the mailing referenced by the given ID. <br />
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $senderalias
     *  the sender alias to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setSenderAlias($mailingId, $senderalias)
    {
        return $this->post('mailings/' . $mailingId . '/contents/senderalias', "<senderalias>" . $senderalias . "</senderalias>");
    }

    /**
     * Updates the recipientalias of the mailing referenced by the given ID. <br />
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $recipientalias
     *  the recipient alias to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setRecipientAlias($mailingId, $recipientalias)
    {
        return $this->post('mailings/' . $mailingId . '/contents/recipientalias', "<recipientalias>" . $recipientalias . "</recipientalias>");
    }

    /**
     * Fetches the reply-to address of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the reply-to address of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getReplyToAddress($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/settings/replyto');
    }

    /**
     * Sets the reply-to address of the mailing identified by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing
     * @param bool $auto (default = true)
     *  If true, the Maileon autorecognition will be used and emails will be saved within Maileon. If false, a custom email address can be passed which gets all mails forwarded.
     * @param string $customEmail (default = empty)
     *  If $auto is false, this email will be used for manual responses.
     * @return
     * @throws MaileonException
     */
    function setReplyToAddress($mailingId, $auto = true, $customEmail = null)
    {
        $queryParameters = array(
            'auto' => ($auto == TRUE) ? "true" : "false",
            'customEmail' => $customEmail
        );

        return $this->post('mailings/' . $mailingId . '/settings/replyto', null, $queryParameters);
    }

    /**
     * Types can be selected from 'doi','trigger', 'trigger_template' or 'regular' <br />
     * <br />
     * @see MailingFields
     *
     * @param string $scheduleTime
     *  This is a date and time string that defines the filter for a mailing. The mailings before and after that time can be queried, see beforeSchedulingTime. The format is the standard SQL date: yyyy-MM-dd HH:mm:ss
     * @param bool $beforeSchedulingTime (default = true)
     *  If true, the mailings before the given time will be returned, if false, the mailings at or after the given time will be returned.
     * @param string[] fields (default = empty)
     *  This list contains the fields that shall be returned with the result. If this list is empty, only the IDs will be returned. Valid fields are: state, type, name, and scheduleTime
     * @param number page_index (default = 1)
     *  The index of the result page. The index must be greater or equal to 1.
     * @param number page_size (default = 100)
     *    The maximum count of items in the result page. If provided, the value of page_size must be in the range 1 to 1000.
     * @param string orderBy (default = id)
     *    The field to order results by
     * @param string order (default = DESC)
     *    The order
     * @return
     * @throws MaileonException
     */
    function getMailingsBySchedulingTime($scheduleTime, $beforeSchedulingTime = true, $fields = array(), $page_index = 1, $page_size = 100, $orderBy = "id", $order = "DESC")
    {
        $queryParameters = array(
            'page_index' => $page_index,
            'page_size' => $page_size,
            'scheduleTime' => urlencode($scheduleTime),
            'beforeSchedulingTime' => ($beforeSchedulingTime == TRUE) ? "true" : "false",
            'orderBy' => $orderBy,
            'order' => $order
        );

        $queryParameters = $this->appendArrayFields($queryParameters, "fields", $fields);

        return $this->get('mailings/filter/scheduletime', $queryParameters);
    }

    /**
     * Types can be selected from 'doi','trigger', 'trigger_template' or 'regular' <br />
     * <br />
     * @see MailingFields
     *
     * @param string[] $types
     *  This is the list of types to filter for
     * @param string[] fields (default = empty)
     *  This list contains the fields that shall be returned with the result. If this list is empty, only the IDs will be returned. Valid fields are: state, type, name, and scheduleTime
     * @param number page_index (default = 1)
     *  The index of the result page. The index must be greater or equal to 1.
     * @param number page_size (default = 100)
     *    The maximum count of items in the result page. If provided, the value of page_size must be in the range 1 to 1000.
     * @return
     * @throws MaileonException
     */
    function getMailingsByTypes($types, $fields = array(), $page_index = 1, $page_size = 100)
    {
        $queryParameters = array(
            'page_index' => $page_index,
            'page_size' => $page_size,
            'order' => "DESC"
        );

        $queryParameters = $this->appendArrayFields($queryParameters, "types", $types);
        $queryParameters = $this->appendArrayFields($queryParameters, "fields", $fields);

        return $this->get('mailings/filter/types', $queryParameters);
    }

    /**
     * Schedules the mailing to be instantly sent
     *
     * @param number mailingId
     *  The ID of the mailing to send now
     * @return
     * @throws MaileonException
     */
    function sendMailingNow($mailingId)
    {
        return $this->post('mailings/' . $mailingId . '/sendnow');
    }



    /**
     * Fetches the DOI mailing key of the mailing identified by the given ID.
     *
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call, with the target group id of the mailing
     *  available at com_maileon_api_MaileonAPIResult::getResult()
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function getDoiMailingKey($mailingId)
    {
        return $this->get('mailings/' . $mailingId . '/settings/doi_key', null, "text/html");
    }

    /**
     * Sets the key of the DOI mailing identified by the given ID.
     *
     * @param number $mailingId
     *  the ID of the mailing
     * @param string $doiKey
     *  The new DOI key.
     * @return
     * @throws MaileonException
     */
    function setDoiMailingKey($mailingId, $doiKey)
    {
        return $this->post('mailings/' . $mailingId . '/settings/doi_key', "<doi_key>$doiKey</doi_key>");
    }

    /**
     * Deactivates a trigger mailing by ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function deactivateTriggerMailing($mailingId)
    {
        return $this->delete("mailings/${mailingId}/dispatching");
    }



    /**
     * Get the dispatch data for a trigger mailing by mailing ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function getTriggerDispatchLogic($mailingId)
    {
        return $this->get("mailings/${mailingId}/dispatching");
    }


    /**
     * Get the schedule for regular mailings by mailing ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function getSchedule($mailingId)
    {
        return $this->get("mailings/${mailingId}/schedule");
    }

    /**
     * Get the archive url for the mailing ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function getArchiveUrl($mailingId)
    {
        return $this->get("mailings/${mailingId}/archiveurl");
    }

    /**
     * Updates the name of the mailing referenced by the given ID.
     *
     * @param string $mailingId
     *  the ID of the mailing to update
     * @param string $name
     *  the name of the mailing to set
     * @return \em com_maileon_api_MaileonAPIResult
     *    the result object of the API call
     * @throws com_maileon_api_MaileonAPIException
     *  if there was a connection problem or a server error occurred
     */
    function setName($mailingId, $name)
    {
        return $this->post('mailings/' . $mailingId . '/name', "<name>" . $name . "</name>");
    }

    /**
     * Get the name for the mailing by mailing ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function getName($mailingId)
    {
        return $this->get("mailings/${mailingId}/name");
    }

    /**
     * Copy the mailing with the given mailing ID.
     * @param number $mailingId
     *  the ID of the mailing
     * @return \em com_maileon_api_MaileonAPIResult
     *  the result of the operation
     */
    function copyMailing($mailingId)
    {
        return $this->post("mailings/${mailingId}/copy");
    }
}
