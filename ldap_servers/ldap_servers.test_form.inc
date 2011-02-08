<?php
// $Id$


/**
 * @file
 * unfinished and depracated.  needs a look
 *
 */

/**
 * Implements the LDAP server test page.
 *
 * @param $form_state
 *   A form state array.
 * @param $op
 *   An operatin - add or edit.
 * @param $sid
 *   A LDAP server ID.
 *
 * @return
 *   The form structure.
 */

function ldap_servers_test_form($form, &$form_state, $op = NULL, $sid = NULL) {

$ldap_server = ldap_servers_get_servers($sid, 'all', TRUE); 
        
$form['#prefix'] = t('<h1>Test %name LDAP Server Configuration</h1>', array('%name' => $ldap_server->name));

$form['#prefix'] .= t('This form simply tests an LDAP configuration to see if
  it can bind and if the sample username is mapped correctly.  It does not
  alter the settings.');

$variables = array(
      'ldap_server' => $ldap_server, 'actions' => FALSE, 'type' => 'detail'
    );

$form['#prefix'] .= theme('ldap_servers_server', $variables);



  $form['sid'] = array(
      '#type' => 'hidden',
      '#default_value' => $sid,
    );

  
  $form['binding']['bindpw'] = array(
    '#type' => 'password',
    '#title' => t('Password for non-anonymous search'),
    '#size' => 20,
    '#maxlength' => 255
  );
  
  if ( $form['binding']['bindpw']) {
    $form['binding']['bindpw']['#description'] = t('<p>Leave emtpy to test with currently stored password.</p>');
  }

  $form['testing_drupal_username'] = array(
    '#type' => 'textfield',
    '#title' => t('Testing Drupal Username'),
    '#default_value' => $ldap_server->testingDrupalUsername,
    '#size' => 30,
    '#maxlength' => 255,
    '#description' => t('This is optional and used for testing this server\'s configuration against an actual username.  The user need not exist in Drupal and testing will not affect the user\'s LDAP or Drupal Account.'),
  );
  

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => 'test',
    '#weight' => 100,
  );

  return $form;
}

/**
 * Validate hook for the LDAP server form.
 */
function ldap_servers_test_form_validate($form, &$form_state) {
  $values = $form_state['values'];
}

/**
 * Submit hook for the LDAP server form.
 */
function ldap_servers_test_form_submit($form, &$form_state) {
  $errors = FALSE;
  $values = $form_state['values'];
  $sid = $values['sid'];
  $ldap_server = ldap_servers_get_servers($sid, 'all', TRUE); 
  $fields = ldap_servers_fields();
  //$result = t('<h1>Test of name </h2>',$server_conf);
  $results = array();
  if ($values['bindpw']) {
    $bindpw = $values['bindpw'];
    $bindpw_type = t('entered in form.');
  } 
  else {
    $bindpw = NULL;
    $bindpw_type = t('stored in configuration');
  }

  $results[] = t('Binding with DN for non-anonymous search (%bind_dn).  Using password ', array('%bind_dn' =>  $ldap_server->binddn)) . ' ' . $bindpw_type;

  // connect to ldap
  // $ldap_server = NULL;
  list($has_errors, $more_results) = ldap_servers_test_binding_credentials($ldap_server, $bindpw);
  $results = array_merge($results, $more_results);

  if (!$has_errors) {
    list($has_errors, $more_results, $ldap_user) = ldap_servers_test_user_mapping($values['testing_drupal_username'], $ldap_server);
    $results = array_merge($results, $more_results);

    if (!$has_errors) {
      $mapping[] = "dn = " . $ldap_user['dn'];
      $mapping[] = "email = " . $ldap_user['mail'];

      foreach ($ldap_user['attr'] as $key => $value) {
        if (is_array($value)) {
          $mapping[] = "$key = " . $value[0];
        }
      }
    // $mapping[] = theme_item_list(array('items' => $list, 'title' => 'Attributes', 'type' => 'ul', 'attributes' => array()));
    $results[] =  theme_item_list(array('items' => $mapping, 'title' => t('Attributes available to %bind_dn', array('%bind_dn' =>  $ldap_server->binddn)) , 'type' => 'ul', 'attributes' => array()));
    }
  }

  $title =  t('Test %name LDAP Server Configuration', array('%name' => $ldap_server->name));
  $message =  theme_item_list(   array('items' => $results, 'title' => $title, 'type' => 'ul', 'attributes' => array()));
  drupal_set_message(t("<form>" . $message . "</form>"), ($has_errors) ? 'error' : 'status');
  $form_state['redirect'] = LDAP_SERVERS_MENU_BASE_PATH . "/servers/test/$sid";

}


function ldap_servers_test_binding_credentials(&$ldap_server, $bindpw) {

  $errors = FALSE;
  $results = array();

  $ldap_result = $ldap_server->connect();
  if ($ldap_result != LDAP_SUCCESS) {
    $results[] = "Failed to connect to LDAP server.  See watchdog error logs for details";
    $errors = TRUE;
  }

  if (!$errors) {
    $bind_result = $ldap_server->bind($ldap_server->binddn, $bindpw);
    if ($bind_result == LDAP_SUCCESS) {
      $results[] =  t('Successfully bound to server');
    } 
    else {
      $results[] = t('Failed to bind to server. ldap error #') . $bind_result;
      $errors = TRUE;
    }
  }

  return array($errors, $results);
}

function ldap_servers_test_user_mapping($drupal_username, &$ldap_server) {
  $ldap_user = $ldap_server->user_lookup($drupal_username);
  $errors = FALSE;
  if (! $ldap_user ) {
    $results[] = t('Failed to find test user %username by searching on  %user_attr = %username.', array('%username' => $drupal_username, '%user_attr' => $ldap_server->user_attr ));
    $errors = TRUE;
  }
  else {
    $results[] = t('Found test user %username by searching on  %user_attr = %username.', array('%username' => $drupal_username, '%user_attr' => $ldap_server->user_attr ));
  }
  return array($errors, $results, $ldap_user);
}


