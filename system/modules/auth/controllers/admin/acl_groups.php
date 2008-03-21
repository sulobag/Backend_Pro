<?php if (!defined('BASEPATH')) exit('No direct script access allowed');  
    /**
     * BackendPro
     *
     * A website backend system for developers for PHP 4.3.2 or newer
     *
     * @package         BackendPro
     * @author          Adam Price
     * @copyright       Copyright (c) 2008
     * @license         http://www.gnu.org/licenses/lgpl.html
     * @tutorial        BackendPro.pkg
     */

     // ---------------------------------------------------------------------------

    /**
     * ACL Groups
     * 
     * Provide the ability to manage ACL groups
     *
     * @package         BackendPro
     * @subpackage      Controllers
     */     
     class Acl_groups extends Admin_Controller
     {
         function Acl_groups()
         {
             // Call parent constructor
             parent::Admin_Controller();
             
             // Load files
             $this->lang->load('access_control');
             $this->load->model('access_control_model'); 
             
             // Set breadcrumb
             $this->page->set_crumb($this->lang->line('backendpro_access_control'),'auth/admin/access_control');
             $this->page->set_crumb($this->lang->line('access_groups'),'auth/admin/acl_groups'); 
             
             // Check for access permission
             check('Groups');
             
             log_message('debug','ACL Groups Cass Initialized'); 
         }
         
         /**
          * View Groups
          * 
          * @access public
          * @return void 
          */
         function index()
         {                                       
             // Display Page
             $data['header'] = $this->lang->line('access_groups');
             $data['page'] = $this->config->item('backendpro_template_admin') . "access_control/groups";
             $data['module'] = 'auth';
             $this->load->view(Site_Controller::$_container,$data);
         }
         
         /**
          * Group form
          * 
          * @access public
          * @param integer $id Group ID
          * @return void 
          */
         function form($id = NULL)
         {             
             // Setup form validation
             $this->load->library('validation');
             $fields['id'] = "ID";
             $fields['name'] = $this->lang->line('access_name'); 
             $fields['disabled'] = $this->lang->line('access_disabled'); 
             $fields['parent'] = $this->lang->line('access_parent_name');
             $this->validation->set_fields($fields);
             
             $rules['name'] = "trim|required|max_length[254]";
             $rules['parent'] = "required";
             
             if( ! is_null($id) AND ! $this->input->post('submit'))
             {
                 // Load values into form
                 $node = $this->access_control_model->group->getNodeFromId($id);
                 
                 // Check it isn't the root
                 if( $this->access_control_model->group->checkNodeIsRoot($node)){
                     flashMsg('warning',sprintf($this->lang->line('access_group_root'),$node['name']));
                     redirect('auth/admin/acl_groups');
                 }
                 
                 // Fetch the disabled value
                 $query = $this->access_control_model->fetch('groups','disabled',NULL,array('id'=>$id));
                 $row = $query->row();
                 
                 $parent = $this->access_control_model->group->getAncestor($node);
                 $this->validation->set_default_value('id',$id);    
                 $this->validation->set_default_value('disabled',$row->disabled);    
                 $this->validation->set_default_value('name',$node['name']); 
                 $this->validation->set_default_value('parent',$parent['name']); 
             }
             elseif( is_null($id) AND ! $this->input->post('submit'))
             {
                 // Create form, first load
                 $this->validation->set_default_value('disabled','0');     
             }
             elseif( $this->input->post('submit'))
             {
                 // Form submited, check rules
                 $this->validation->set_rules($rules);
             }
             
             if($this->validation->run() === FALSE)
             {
                 // Display Errors
                 $this->validation->output_errors();
                 
                 // Get Resources
                 $data['groups'] = $this->access_control_model->buildACLDropdown('group');
                 
                 // Display Page  
                 $data['header'] = (is_null($id)?$this->lang->line('access_create_group'):$this->lang->line('access_edit_group'));
                 $this->page->set_crumb($data['header'],'auth/admin/acl_groups/form/'.$id);   
                 $data['page'] = $this->config->item('backendpro_template_admin') . "access_control/form_group";
                 $data['module'] = 'auth';
                 $this->load->view(Site_Controller::$_container,$data);
             }
             else
             {   
                 $name = $this->input->post('name');  
                 $disabled = $this->input->post('disabled');   
                 $parent = $this->input->post('parent'); 
                 
                 if( is_null($id))
                 {            
                     // Create Group
                     $this->load->library('khacl');                     
                     
                     $this->db->trans_start();
                     if( ! $this->khacl->aro->create($name,$parent))
                     {
                         flashMsg('warning',sprintf($this->lang->line('access_group_exists'),$name));
                         redirect('auth/admin/acl_groups/form'); 
                     }  
                     
                     $this->access_control_model->insert('groups',array('id'=>$this->db->insert_id(),'disabled'=>$disabled));
                     
                     if( $this->db->trans_status() === TRUE)
                     {
                         $this->db->trans_commit();
                         flashMsg('success',sprintf($this->lang->line('access_group_created'),$name)); 
                     }
                     else
                     {
                         $this->db->trans_rollback(); 
                         flashMsg('error',sprintf($this->lang->line('backendpro_action_failed'),$this->lang->line('access_create_group'))); 
                     }  
                 }
                 else
                 {
                     $id = $this->input->post('id');
                     // Update Group
                     $node = $this->access_control_model->group->getNodeFromId($id);
                     $new_parent = $this->access_control_model->group->getNodeWhere("name='".$parent."'");
                     
                     // Check the assigment isn't illeagal
                     if($this->access_control_model->group->checkNodeIsChildOrEqual($new_parent,$node)){
                        flashMsg('warning',sprintf($this->lang->line('access_group_illegal_assignment'),$name));
                        redirect('auth/admin/acl_groups/form/'.$id);   
                     }
                     
                     $this->db->trans_start();
                     
                     $this->access_control_model->group->setNodeAsLastChild($node,$new_parent);
                     $this->access_control_model->update('groups',array('disabled'=>$disabled),array('id'=>$id)); 
                     $this->access_control_model->update('aros',array('name'=>$name),array('id'=>$id)); 
                     
                     if($this->db->trans_status() === TRUE)
                     {
                         $this->db->trans_commit();
                         flashMsg('success',sprintf($this->lang->line('access_group_saved'),$name));
                     }
                     else
                     {
                         $this->db->trans_rollback();
                         flashMsg('error',sprintf($this->lang->line('backendpro_action_failed'),$this->lang->line('access_edit_group')));
                     } 
                 }
                 redirect('auth/admin/acl_groups');
             }
         }
         
         /**
          * Delete Groups
          * 
          * @access public
          * @return void 
          */
         function delete()
         {
             if(FALSE === ($groups = $this->input->post('select')))
                redirect('auth/admin/acl_groups','location');   
             
             $this->load->library('khacl');
             foreach($groups as $group)
             {
                 // Check we havn't already deleted it as a child of another node
                 $query = $this->access_control_model->fetch('aros',NULL,NULL,array('name'=>$group));
                 if($query->num_rows() === 0){
                    flashMsg('success',sprintf($this->lang->line('access_group_deleted'),$group));
                    continue;
                 }  
                 
                 if( $this->khacl->aro->delete($group))
                    flashMsg('success',sprintf($this->lang->line('access_group_deleted'),$group));
                 else
                    flashMsg('error',sprintf($this->lang->line('backendpro_action_failed'),$this->lang->line('access_delete_group')));
             }
             
             redirect('auth/admin/acl_groups','location');
         }    
     }
?>