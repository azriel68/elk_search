<?php

class ELKContact extends contact {

    function fetch($id, $user = 0, $ref_ext = '')
    {
        $this->loaded_by_elk = 0;

        if(empty($id)) return parent::fetch($id, $user, $ref_ext);

        $obj = ELKParser::fetch($this, $id);

        if(false === $obj) return parent::fetch($id, $user, $ref_ext);
        else {

            ELKParser::setObjectByStorage($this,$obj);
            $this->loaded_by_elk = 1;
            return 1;
        }

        return 0;

    }

    function load_previous_next_ref($filter,$fieldid,$nodbprefix=0) {
        return false;
    }

    function update_note($note, $suffix='') {
        $res = parent::update_note($note,$suffix);

        $result=$this->call_trigger('CONTACT_NOTE_MODIFY',$user);
        if ($result < 0) $error++;

        return $res;
    }
}