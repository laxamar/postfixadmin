<?php

/**
 * Handler for recipient blacklist entries
 */
class BlacklistHandler extends PFAHandler
{
    protected $db_table = 'mysql_virtual_recipient_blacklist';
    protected $id_field = 'address';
    protected $domain_field = 'domain';
    protected $order_by = 'address';
    protected $searchfields = array('address');

    protected function initStruct()
    {
        $this->struct = array(
            # field name                allow       display in...   type     $PALANG label           $PALANG description          default / options / ...
            #                           editing?    form    list
            'address'          => pacol($this->new, 1,      1,      'text', 'pBlacklist_address_field' , 'pBlacklist_address_desc'   , ''),
            'action'           => pacol(1,          1,      1,      'enum', 'pBlacklist_action'    , 'pBlacklist_action_desc'    , 'REJECT', 
                array('REJECT', 'DISCARD', 'DEFER')),
            'active'           => pacol(1,          1,      1,      'bool', 'active'               , ''                          , 1),
            'domain'           => pacol(0,          0,      1,      'text', 'domain'               , ''                          , '', array(),
                array('dont_write_to_db' => 1)), # domain is a generated column
            'created'          => pacol(0,          0,      1,      'ts',   'created'              , ''),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'        , ''),
        );
    }

    protected function initMsg()
    {
        $this->msg['error_already_exists'] = 'pBlacklist_address_already_exists';
        $this->msg['error_does_not_exist'] = 'pBlacklist_address_does_not_exist';
        $this->msg['confirm_delete'] = 'pBlacklist_confirm_delete';
        $this->msg['list_header'] = 'pMenu_blacklist';

        if ($this->new) {
            $this->msg['logname'] = 'create_blacklist_entry';
            $this->msg['store_error'] = 'pBlacklist_database_save_error';
            $this->msg['successmessage'] = 'pBlacklist_database_save_success';
        } else {
            $this->msg['logname'] = 'edit_blacklist_entry';
            $this->msg['store_error'] = 'pBlacklist_database_save_error';
            $this->msg['successmessage'] = 'pBlacklist_database_save_success';
        }
    }

    public function webformConfig()
    {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pBlacklist_new_entry',
            'formtitle_edit' => 'pBlacklist_edit_entry',
            'create_button' => 'pBlacklist_new_entry',
            'cancel_button' => 'pBlacklist_cancel_button',

            # various settings
            'required_role' => 'global-admin',
            'listview' => 'list.php?table=blacklist',
            'early_init' => 0,
        );
    }

    protected function validate_new_id()
    {
        if ($this->id == '') {
            $this->errormsg[$this->id_field] = 'pBlacklist_address_empty';
            return false;
        }

        # Validate email address or domain pattern
        if (!filter_var($this->id, FILTER_VALIDATE_EMAIL) && !preg_match('/^@[\w\.-]+$/', $this->id)) {
            $this->errormsg[$this->id_field] = 'pBlacklist_address_invalid';
            return false;
        }

        return true;
    }

    protected function domain_from_id()
    {
        # Extract domain from address or domain pattern
        if (strpos($this->id, '@') === 0) {
            # Domain pattern like @spam.com
            return substr($this->id, 1);
        } elseif (strpos($this->id, '@') !== false) {
            # Full email address
            return substr($this->id, strrpos($this->id, '@') + 1);
        }
        return '';
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function postSave(): bool
    {
        # Domain field is auto-populated by the generated column
        return true;
    }

    /**
     * @return boolean
     */
    public function delete()
    {
        if (!$this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log('', 'delete_blacklist_entry', $this->result['address'] ?? '');
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['address']);
        return true;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
