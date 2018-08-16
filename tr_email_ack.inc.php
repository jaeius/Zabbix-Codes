<?php
# for Zabbix 2.0

function get_hostname_and_ip_by_triggerid($tr_id){
        $ret = DBfetch(DBselect("SELECT  host, ip,min(n.interfaceid), maintenance_status FROM triggers t INNER JOIN functions f ON ( f.triggerid = t.triggerid ) INNER JOIN items i ON ( i.itemid = f.itemid ) INNER JOIN hosts h ON ( i.hostid = h.hostid ) INNER JOIN interface n ON ( n.hostid = h.hostid ) WHERE t.triggerid = $tr_id"));
        return $ret;
}



function get_eventid_clock_by_triggerid($tr_ids){
        $i = 0;
        $where = "";
        foreach($tr_ids as $tr_id){
                        $i++;
                        if(sizeof($tr_ids) == $i){ $where .= " $tr_id"; }
                        else {$where .= "$tr_id,";}
                }
         $ret = DBselect("SELECT min(e.clock), min(e.eventid), e.objectid, t.priority
                        FROM events e INNER JOIN triggers t ON ( t.triggerid = e.objectid )
                        WHERE e.objectid IN ($where)  AND e.value=1 AND e.acknowledged=0 AND e.object=0
                        AND e.clock >= unix_timestamp(DATE_SUB(now(), INTERVAL 1 DAY))
                        GROUP BY e.objectid, t.priority;");
        return $ret;
}

function get_media_smtp_server($media_desc){
        $media_smtp_server = DBfetch(DBselect("SELECT smtp_server FROM media_type WHERE description='$media_desc';"));
        return $media_smtp_server['smtp_server'];
}

function get_media_smtp_email($media_desc){
        $media_smtp_email = DBfetch(DBselect("SELECT smtp_email FROM media_type WHERE description='$media_desc';"));
        return $media_smtp_email['smtp_email'];
}

function get_media_smtp_ack_recipient($user_alias){
        $media_smtp_ack_recipient = DBselect("SELECT sendto FROM media m INNER JOIN users u ON (m.userid = u.userid) WHERE u.alias='$user_alias';");
        while($e = DBfetch($media_smtp_ack_recipient) ){
                $email_to_address .= $e['sendto'].", ";
        }
        return $email_to_address;
}

function send_email_ack($tr_id, $ev_id, $time_first, $time_ack, $severity, $NOC_name){
        #========================================================================================
        $email_from_address_name = get_media_smtp_server("Email Ack Sender");
        $email_from_address      = get_media_smtp_email("Email Ack Sender");
        $email_to_address        = get_media_smtp_ack_recipient("Email Ack Recipient");
        #========================================================================================

        $res = get_hostname_and_ip_by_triggerid($tr_id);
        $tr_hostname = $res['host'];
        $tr_ip = $res['ip'];
        $maint_mode = $res['maintenance_status'];
        $time_diff =  $time_ack - $time_first;
        $time_elapsed_sec = $time_diff % 60;
        $time_elapsed_min = floor($time_diff / 60);
$headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: '.$email_from_address_name.' <'.$email_from_address.'>'."\r\n";

                $mail_body = "<b>Comment:</b><br />".$_REQUEST['message']."<br /><br />
                              <b>Trigger:</b> [".CTriggerHelper::expandDescriptionById($tr_id)."] <br />
                              <b>Trigger ID:</b> [$tr_id] <br />
                              <b>Severity:</b> [$severity] <br/><br />

                              <b>Time Last Change:</b> [".date("Y-m-d g:i:s a",$time_first )."] <br />
                              <b>Time Acknowledged:</b> [".date("Y-m-d g:i:s a", $time_ack)."] <br />
                              <b>Time Elapsed:</b> [$time_elapsed_min min $time_elapsed_sec sec] <br /><br />
                              <b>Host:</b> [$tr_hostname]<br /> <b>IP:</b>[$tr_ip] <br /><br />

                              <p><span style=\"color: #3366ff;\">Regards, </span><br /><br /><span style=\"color:#000000;\">".$NOC_name."</span><br />
                              <span style=\"color: #3366ff;\"><em>Ring<span style=\"color: #ff6600;\">Central</span></em>, NOC</span><br />
                              <span style=\"color: #3366ff;\">NOC Hotline: 1.650.458.4484</span></p>
                              <p><span style=\"color: #3366ff;\">Desk Number: 1.650.763.3864</span><br /><br /><br /></p>";

        if(!$maint_mode) {mail($email_to_address,"NOC Informational: Alert Verification: $tr_hostname : [$tr_ip] : $tr_desc :".CTriggerHelper::expandDescriptionById($tr_id)." : ".date("Y-m-d H:i:s", $time_ack)." : $ev_id", $mail_body, $headers);
        }
}
?>
