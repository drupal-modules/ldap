<?php

namespace Drupal\ldap_servers\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\ldap_test\TestServer;
use Drupal\ldap_test\LdapTestCase;

/**
 * Tests covering ldap_server module.
 *
 * @group ldap_servers
 */
class LdapServersTestCase extends LdapTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'LDAP Servers Tests',
      'description' => 'Test ldap servers.  Servers module is primarily a storage
        tool for ldap server configuration, so most of testing is just form and db testing.
        there are some api like functions that are also tested.',
      'group' => 'LDAP',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct($test_id = NULL) {
    parent::__construct($test_id);
  }

  public $module_name = 'ldap_servers';
  protected $ldap_test_data;

  public static $modules = array('ldap_test', 'ldap_servers');

  /**
   * Create one or more server configurations in such as way
   *  that this setUp can be a prerequisite for ldap_authentication and ldap_authorization.
   *    * Function setUp() {
   * parent::setUp(array('ldap_test'));
   * variable_set('ldap_simpletest', 2);
   * }
   *    * function tearDown() {
   * parent::tearDown();
   * variable_del('ldap_help_watchdog_detail');
   * variable_del('ldap_simpletest');
   * }.
   */
  public function testApiFunctions() {

    $group = 'ldap_servers: functions';

    // , 'activedirectory1'.
    foreach (array('openldap1', 'activedirectory1') as $sid) {
      $ldap_type = ($sid == 'openldap1') ? 'Open Ldap' : 'Active Directory';
      $this->prepTestData('hogwarts', array($sid));

      $group = "ldap_servers: functions: $ldap_type";
      // @FIXME $test_data = variable_get('ldap_test_server__' . $sid, array());
      $ldap_server = TestServer::getLdapServerObjects($sid, NULL, TRUE);

      // Check against csv data rather than ldap array to make sure csv to ldap conversion is correct.
      // @FIXME: Remove line below when fixed above
      $test_data['csv']['users']['101'] = 'temp';
      $user_csv_entry = $test_data['csv']['users']['101'];
      $user_dn = $user_csv_entry['dn'];
      $user_cn = $user_csv_entry['cn'];
      $user_ldap_entry = $test_data['ldap'][$user_dn];

      $username = $ldap_server->userUsernameFromLdapEntry($user_ldap_entry);
      $this->assertTrue($username == $user_csv_entry['cn'], 'LdapServer::userUsernameFromLdapEntry works when LdapServer::user_attr attribute used', $group);

      $bogus_ldap_entry = array();
      $username = $ldap_server->userUsernameFromLdapEntry($bogus_ldap_entry);
      $this->assertTrue($username === FALSE, 'LdapServer::userUsernameFromLdapEntry fails correctly', $group);

      $username = $ldap_server->userUsernameFromDn($user_dn);
      $this->assertTrue($username == $user_cn, 'LdapServer::userUsernameFromDn works when LdapServer::user_attr attribute used', $group);

      $username = $ldap_server->userUsernameFromDn('bogus dn');
      $this->assertTrue($username === FALSE, 'LdapServer::userUsernameFromDn fails correctly', $group);

      $desired = array();
      $desired[0] = array(
        0 => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
        1 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        2 => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
      );
      $desired[1] = array_merge($desired[0], array('cn=users,ou=groups,dc=hogwarts,dc=edu'));

      foreach (array(0, 1) as $nested) {

        $nested_display = ($nested) ? 'nested' : 'not nested';
        $desired_count = ($nested) ? 4 : 3;
        $ldap_module_user_entry = array('attr' => $user_ldap_entry, 'dn' => $user_dn);
        $groups_desired = $desired[$nested];

        $suffix = ",desired=$desired_count, nested=" . (boolean) $nested;

        // Test parent function groupMembershipsFromUser.
        $groups = $ldap_server->groupMembershipsFromUser($ldap_module_user_entry, 'group_dns', $nested);
        $count = count($groups);
        $diff1 = array_diff($groups_desired, $groups);
        $diff2 = array_diff($groups, $groups_desired);
        $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
        $this->assertTrue($pass, "LdapServer::groupMembershipsFromUser nested=$nested", $group . $suffix);
        if (!$pass) {
          debug('groupMembershipsFromUser');debug($groups);  debug($diff1);  debug($diff2);  debug($groups_desired);
        }

        // Test parent groupUserMembershipsFromUserAttr, for openldap should be false, for ad should work.
        $groups = $ldap_server->groupUserMembershipsFromUserAttr($ldap_module_user_entry, $nested);
        $count = is_array($groups) ? count($groups) : $count;
        $pass = $count === FALSE;
        if ($sid == 'openldap1') {
          $pass = ($groups === FALSE);
        }
        else {
          $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
        }
        $this->assertTrue($pass, "LdapServer::groupUserMembershipsFromUserAttr $nested_display, $ldap_type, is false because not configured", $group . $suffix);
        if (!$pass) {
          debug('groupUserMembershipsFromUserAttr');debug($groups);  debug($diff1);  debug($diff2);
        }

        $groups = $ldap_server->groupUserMembershipsFromEntry($ldap_module_user_entry, $nested);
        $count = count($groups);
        $diff1 = array_diff($groups_desired, $groups);
        $diff2 = array_diff($groups, $groups_desired);
        $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
        $this->assertTrue($pass, "LdapServer::groupUserMembershipsFromEntry $nested_display works", $group . $suffix);
        if (!$pass) {
          debug('groupUserMembershipsFromEntry'); debug($groups);  debug($diff1);  debug($diff2);  debug($groups_desired);
        }
      }
    }
  }

  /**
   *
   */
  public function testInstall() {
    $group = 'ldap_servers: install and uninstall';
    $install_tables = array('ldap_servers');
    // disable, uninstall, and enable/install module.
    $modules = array($this->module_name);
    $module_installer = ModuleInstaller();
    $ldap_module_uninstall_sequence = array('ldap_authentication', 'ldap_test', 'ldap_user', 'ldap_group', 'ldap_servers');
    // Uninstall dependent modules.
    $module_installer->uninstall($modules, TRUE);
    // Uninstall dependent modules.
    $module_installer->install($modules, TRUE);
    foreach ($install_tables as $table) {
      $this->assertTrue(db_table_exists($table), $table . ' table creates', $group);
    }

    // Unistall dependent modules.
    $module_installer->uninstall($modules, TRUE);
    foreach ($install_tables as $table) {
      $this->assertFalse(db_table_exists($table), $table . ' table removed', $group);
    }

    // Test tokens, see http://drupal.org/node/1245736
    $ldap_entry = array(
      'dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
      'mail' => array(0 => 'hpotter@hogwarts.edu', 'count' => 1),
      'sAMAccountName' => array(0 => 'hpotter', 'count' => 1),
      'house' => array(0 => 'Gryffindor', 1 => 'Privet Drive', 'count' => 2),
      'guid' => array(0 => 'sdafsdfsdf', 'count' => 1),
      'count' => 3,
    );

    $this->ldapTestId = 'ldap_server.tokens';

    $dn = ldap_servers_token_replace($ldap_entry, '[dn]');
    $this->assertTrue($dn == $ldap_entry['dn'], t('[dn] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $house0 = ldap_servers_token_replace($ldap_entry, '[house:0]');
    $this->assertTrue($house0 == $ldap_entry['house'][0], t("[house:0] token worked ($house0) on ldap_servers_token_replace()."), $this->ldapTestId);

    $mixed = ldap_servers_token_replace($ldap_entry, 'thisold[house:0]');
    $this->assertTrue($mixed == 'thisold' . $ldap_entry['house'][0], t("thisold[house:0] token worked ($mixed) on ldap_servers_token_replace()."), $this->ldapTestId);

    $compound = ldap_servers_token_replace($ldap_entry, '[samaccountname:0][house:0]');
    $this->assertTrue($compound == $ldap_entry['sAMAccountName'][0] . $ldap_entry['house'][0], t("[samaccountname:0][house:0] compound token worked ($mixed) on ldap_servers_token_replace()."), $this->ldapTestId);

    $literalvalue = ldap_servers_token_replace($ldap_entry, 'literalvalue');
    $this->assertTrue($literalvalue == 'literalvalue', t("'literalvalue' token worked ($literalvalue) on ldap_servers_token_replace()."), $this->ldapTestId);

    $house0 = ldap_servers_token_replace($ldap_entry, '[house]');
    $this->assertTrue($house0 == $ldap_entry['house'][0], t("[house] token worked ($house0) on ldap_servers_token_replace()."), $this->ldapTestId);

    $house1 = ldap_servers_token_replace($ldap_entry, '[house:last]');
    $this->assertTrue($house1 == $ldap_entry['house'][1], t('[house:last] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $sAMAccountName = ldap_servers_token_replace($ldap_entry, '[samaccountname:0]');
    $this->assertTrue($sAMAccountName == $ldap_entry['sAMAccountName'][0], t('[samaccountname:0] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $sAMAccountNameMixedCase = ldap_servers_token_replace($ldap_entry, '[sAMAccountName:0]');
    $this->assertTrue($sAMAccountNameMixedCase == $ldap_entry['sAMAccountName'][0], t('[sAMAccountName:0] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $sAMAccountName2 = ldap_servers_token_replace($ldap_entry, '[samaccountname]');
    $this->assertTrue($sAMAccountName2 == $ldap_entry['sAMAccountName'][0], t('[samaccountname] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $sAMAccountName3 = ldap_servers_token_replace($ldap_entry, '[sAMAccountName]');
    $this->assertTrue($sAMAccountName2 == $ldap_entry['sAMAccountName'][0], t('[sAMAccountName] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $base64encode = ldap_servers_token_replace($ldap_entry, '[guid;base64_encode]');
    $this->assertTrue($base64encode == base64_encode($ldap_entry['guid'][0]), t('[guid;base64_encode] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $bin2hex = ldap_servers_token_replace($ldap_entry, '[guid;bin2hex]');
    $this->assertTrue($bin2hex == bin2hex($ldap_entry['guid'][0]), t('[guid;bin2hex] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $msguid = ldap_servers_token_replace($ldap_entry, '[guid;msguid]');
    $this->assertTrue($msguid == ldap_servers_msguid($ldap_entry['guid'][0]), t('[guid;msguid] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    $binary = ldap_servers_token_replace($ldap_entry, '[guid;binary]');
    $this->assertTrue($binary == ldap_servers_binary($ldap_entry['guid'][0]), t('[guid;binary] token worked on ldap_servers_token_replace().'), $this->ldapTestId);

    /**
     * @todo test tokens for 'user_account'
     *
     * $account = new stdClass();
     * $account->
     * ldap_servers_token_replace($account, '[property.name]', 'user_account');
     */

    module_enable($modules, TRUE);
  }

  /**
   *
   */
  public function testUIForms() {

    foreach (array(1) as $ctools_enabled) {
      $this->ldapTestId = "testUIForms.ctools.$ctools_enabled";
      if ($ctools_enabled) {
        module_enable(array('ctools'));
      }
      else {
        // module_disable(array('ctools'));.
      }

      $ldap_simpletest_initial = config('ldap_test.settings')->get('simpletest');
      // Need to be out of fake server mode to test ui.
      variable_del('ldap_simpletest');
      $this->privileged_user = $this->drupalCreateUser(array(
        'administer site configuration',
      ));
      $this->drupalLogin($this->privileged_user);

      $sid = 'server1';
      $server_data = array();
      $server_data[$sid] = array(
        'sid'        => array($sid, $sid),
        'name'       => array("Server $sid", "My Server $sid"),
        'status'     => array(1, 1),
        'ldap_type'  => array('openldap', 'ad'),
        'address'    => array("${sid}.ldap.fake", "${sid}.ldap.fake"),
        'port'       => array(389, 7000),
        'tls'        => array(TRUE, FALSE),
        'bind_method' => array(1, 3),
        'binddn'  => array('cn=service-account,ou=people,dc=hogwarts,dc=edu', ''),
        'bindpw'  => array('sdfsdafsdfasdf', 'sdfsdafsdfasdf'),
        'user_attr' => array('sAMAccountName', 'blah'),
        'account_name_attr' => array('sAMAccountName', 'blah'),
        'mail_attr' => array('mail', ''),
        'mail_template' => array('' , '[email]'),
        'unique_persistent_attr' => array('dn', 'uniqueregistryid'),
        'unique_persistent_attr_binary' => array(1, 1, 1, 1),
        'user_dn_expression' => array('cn=%cn,%basedn', 'cn=%username,%basedn'),
        'ldap_to_drupal_user' => array('code', 'different code'),

        'testing_drupal_username' => array('hpotter', 'hpotter'),
        'testing_drupal_user_dn' => array('cn=hpotter,ou=people,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=hogwarts,dc=edu'),

        'grp_unused' => array(FALSE, FALSE),
        'grp_object_cat' => array('group', 'group'),
        'grp_nested' => array(FALSE, FALSE),

        'grp_user_memb_attr_exists' => array(1, 1),
        'grp_user_memb_attr' => array('memberof', 'memberof'),

        'grp_memb_attr' => array('member', 'member'),
        'grp_memb_attr_match_user_attr' => array('dn', 'dn'),

        'grp_derive_from_dn' => array(1, 1),
        'grp_derive_from_dn_attr' => array('ou', 'ou'),

        'grp_test_grp_dn' => array('cn=students,ou=groups,dc=hogwarts,dc=edu', 'cn=students,ou=groups,dc=hogwarts,dc=edu'),
        'grp_test_grp_dn_writeable' => array('cn=students,ou=groups,dc=hogwarts,dc=edu', 'cn=students,ou=groups,dc=hogwarts,dc=edu'),

      );

      $lcase_transformed = array(
        'user_attr',
        'account_name_attr',
        'mail_attr',
        'unique_persistent_attr',
        'grp_user_memb_attr',
        'grp_memb_attr_match_user_attr',
        'grp_derive_from_dn_attr',
      );

      if (!module_exists('php')) {
        unset($server_data[$sid]['ldap_to_drupal_user']);
      }

      /** add server conf test **/
      $this->drupalGet('admin/config/people/ldap/servers/add');

      $edit = array();
      foreach ($server_data['server1'] as $input_name => $input_values) {
        $edit[$input_name] = $input_values[0];
      }
      $this->drupalPost('admin/config/people/ldap/servers/add', $edit, t('Add'));
      $field_to_prop_map = LdapServer::field_to_properties_map();
      $field_to_prop_map['bindpw'] = 'bindpw';
      $ldap_servers = ldap_servers_get_servers(NULL, 'all', FALSE, TRUE);
      $this->assertTrue(count(array_keys($ldap_servers)) == 1, 'Add form for ldap server added server.', $this->ldapTestId . ' Add Server');
      $this->assertText('LDAP Server Server server1 added', 'Add form confirmation message', $this->ldapTestId . ' Add Server');
      // Assert one ldap server exists in db table
      // Assert load of server has correct properties for each input.
      $mismatches = $this->compareFormToProperties($ldap_servers['server1'], $server_data['server1'], 0, $field_to_prop_map, $lcase_transformed);
      if (count($mismatches)) {
        debug('mismatches between ldap server properties and form submitted values');
        debug($mismatches);
        debug($ldap_servers);
        debug($server_data['server1']);
      }
      $this->assertTrue(count($mismatches) == 0, 'Add form for ldap server properties match values submitted.', $this->ldapTestId . ' Add Server');

      /** update server conf test **/

      $this->drupalGet('admin/config/people/ldap/servers/edit/server1');

      $edit = array();
      foreach ($server_data['server1'] as $input_name => $input_values) {
        if ($input_values[1] !== NULL) {
          $edit[$input_name] = $input_values[1];
        }
      }

      unset($edit['sid']);
      $this->drupalPost('admin/config/people/ldap/servers/edit/server1', $edit, t('Update'));
      $ldap_servers = ldap_servers_get_servers(NULL, 'all', FALSE, TRUE);
      $this->assertTrue(count(array_keys($ldap_servers)) == 1, 'Update form for ldap server didnt delete or add another server.', $this->ldapTestId . '.Update Server');
      // Assert confirmation message without error
      // assert one ldap server exists in db table
      // assert load of server has correct properties for each input
      // unset($server_data['server1']['bindpw']);.
      $mismatches = $this->compareFormToProperties($ldap_servers['server1'], $server_data['server1'], 1, $field_to_prop_map, $lcase_transformed);
      if (count($mismatches)) {
        debug('mismatches between ldap server properties and form submitted values'); debug($mismatches);
      }
      $this->assertTrue(count($mismatches) == 0, 'Update form for ldap server properties match values submitted.', $this->ldapTestId . '.Update Server');

      /** delete server conf test **/
      $this->drupalGet('admin/config/people/ldap/servers/delete/server1');
      $this->drupalPost('admin/config/people/ldap/servers/delete/server1', array(), t('Delete'));

      $ldap_servers = ldap_servers_get_servers(NULL, 'all', FALSE, TRUE);

      $this->assertTrue(count(array_keys($ldap_servers)) == 0, 'Delete form for ldap server deleted server.', $this->ldapTestId . '.Delete Server');

      // @FIXME: variable_set('ldap_simpletest', $ldap_simpletest_initial); // return to fake server mode
    }
  }

  /**
   *
   */
  public function serverConfCount() {
    $records = db_query('SELECT * FROM {ldap_servers}')->fetchAllAssoc('sid');
    return count(array_keys($records));
  }

  /**
   *
   */
  public function compareFormToProperties($object, $data, $item_id, $map, $lcase_transformed) {

    $mismatches = array();
    foreach ($data as $field_id => $values) {
      $field_id = Unicode::strtolower($field_id);
      if (!isset($map[$field_id])) {
        // debug("no mapping for field: $field_id in item_id $item_id");.
        continue;
      }
      $property = $map[$field_id];
      if (!property_exists($object, $property) && !property_exists($object, Unicode::strtolower($property))) {
        // debug("property $property does not exist in object in item_id $item_id");.
        continue;
      }
      $property_value = $object->{$property};

      // For cases where string input is not same as array.
      $field_value = isset($values[$item_id + 2]) ? $values[$item_id + 2] : $values[$item_id];

      if ($field_id == 'bindpw') {
        continue;
      }
      if ($field_id == 'basedn') {
        $pass = count($property_value) == 2;
        if (!$pass) {
          debug($property_value);
        }
      }
      else {
        if (in_array($field_id, $lcase_transformed) && is_scalar($field_value)) {
          $field_value = Unicode::strtolower($field_value);
        }
        $property_value_show = (is_scalar($property_value)) ? $property_value : serialize($property_value);
        $field_value_show = (is_scalar($field_value)) ? $field_value : serialize($field_value);

        if (is_array($property_value) && is_array($field_value)) {
          $pass = count(array_diff($property_value, $field_value)) == 0;
        }
        elseif (is_scalar($property_value) && is_scalar($field_value)) {
          $pass = ($property_value == $field_value);
        }
        else {
          $pass = FALSE;
        }
      }
      if (!$pass) {
        // @FIXME: not instaniated likely
        $mismatches[] = "property $property ($property_value_show) does not match field $field_id value ($field_value_show)";
      }
    }

    return $mismatches;
  }

}
