<?php

use Civi\Token\TokenProcessor;

/**
 * Class CRM_Activity_Form_Task_PDFLetterCommonTest
 * @group headless
 */
class CRM_Activity_Form_Task_PDFTest extends CiviUnitTestCase {

  /**
   * API version in use.
   *
   * @var int
   */
  protected $_apiversion = 4;

  /**
   * Set up for tests.
   */
  public function setUp(): void {
    parent::setUp();
    $this->useTransaction();
  }

  /**
   * Test create a document with basic tokens.
   */
  public function testCreateDocumentBasicTokens(): void {
    CRM_Core_BAO_ConfigSetting::enableComponent('CiviCase');
    $sourceContactId = $this->individualCreate();
    $case = $this->createCase($sourceContactId);

    $activity = $this->activityCreate([
      'campaign_id' => $this->campaignCreate(),
      'case_id' => $case->id,
      'source_contact_id' => $sourceContactId,
    ]);
    $data = [
      ['Subject: {activity.subject}', 'Subject: Discussion on warm beer'],
      ['Date: {activity.activity_date_time}', 'Date: ' . CRM_Utils_Date::customFormat(date('Ymd'))],
      ['Duration: {activity.duration}', 'Duration: 90'],
      ['Location: {activity.location}', 'Location: Baker Street'],
      ['Details: {activity.details}', 'Details: Lets schedule a meeting'],
      ['Status ID: {activity.status_id}', 'Status ID: 1'],
      ['(legacy) Status: {activity.status}', '(legacy) Status: Scheduled'],
      ['Status: {activity.status_id:label}', 'Status: Scheduled'],
      ['Activity Type ID: {activity.activity_type_id}', 'Activity Type ID: 1'],
      ['(legacy) Activity Type: {activity.activity_type}', '(legacy) Activity Type: Meeting'],
      ['Activity Type: {activity.activity_type_id:label}', 'Activity Type: Meeting'],
      ['(legacy) Activity ID: {activity.activity_id}', '(legacy) Activity ID: ' . $activity['id']],
      ['Activity ID: {activity.id}', 'Activity ID: ' . $activity['id']],
      ['(APIv4 virtual field) Case ID: {activity.case_id}', '(APIv4 virtual field) Case ID: ' . $case->id],
      ['(APIv4 virtual field) Source Contact ID: {activity.source_contact_id}', '(APIv4 virtual field) Source Contact ID: ' . $sourceContactId],
      ['(APIv4 virtual field) Target Contact IDs: {activity.target_contact_id}', '(APIv4 virtual field) Target Contact IDs: ' . $activity['target_contact_id']],
      ['(APIv4 virtual field) Assignee Contact IDs: {activity.assignee_contact_id}', '(APIv4 virtual field) Assignee Contact IDs: ' . $activity['assignee_contact_id']],
    ];
    $tokenProcessor = new TokenProcessor(Civi::dispatcher(), ['schema' => ['activityId']]);

    $this->assertEquals(array_merge($this->getActivityTokens(), CRM_Core_SelectValues::domainTokens()), $tokenProcessor->listTokens());
    $html_message = "\n" . implode("\n", array_column($data, '0')) . "\n";
    $form = $this->getFormObject('CRM_Activity_Form_Task_PDF');
    try {
      $output = $form->createDocument([$activity['id']], $html_message, []);
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      $output = $e->errorData['html'];
    }
    // Check some basic fields
    foreach ($data as $line) {
      $this->assertStringContainsString("\n" . $line[1] . "\n", $output[0]);
    }
  }

  /**
   * Get expected activity Tokens.
   *
   * @return string[]
   */
  protected function getActivityTokens(): array {
    return [
      '{activity.id}' => 'Activity ID',
      '{activity.subject}' => 'Subject',
      '{activity.details}' => 'Details',
      '{activity.activity_date_time}' => 'Activity Date',
      '{activity.created_date}' => 'Created Date',
      '{activity.modified_date}' => 'Modified Date',
      '{activity.location}' => 'Location',
      '{activity.duration}' => 'Duration',
      '{activity.activity_type_id:label}' => 'Activity Type',
      '{activity.status_id:label}' => 'Activity Status',
      '{activity.campaign_id:label}' => 'Campaign',
      '{activity.case_id}' => 'Case ID',
      '{activity.source_contact_id}' => 'Source Contact',
      '{activity.target_contact_id}' => 'Target Contacts',
      '{activity.assignee_contact_id}' => 'Assignee Contacts',
      '{activity.all_contact_id}' => 'Activity Contacts',
      '{activity.target_contact_count}' => 'Target Contact Count',
      '{activity.assignee_contact_count}' => 'Assignee Contact Count',
      '{activity._depth}' => 'Depth',
      '{activity._descendents}' => 'Descendents',
      '{activity.source_checksum}' => 'Checksum (with cs=) (Added By)',
      '{activity.target_N_checksum}' => 'Checksum (with cs=) (With Contact N)',
      '{activity.assignee_N_checksum}' => 'Checksum (with cs=) (Assignee N)',
      '{activity.target_count}' => 'Target Count',
      '{activity.assignee_count}' => 'Assignee Count',
      '{activity.source_checksum_value}' => 'Checksum value (Added By)',
      '{activity.target_N_checksum_value}' => 'Checksum value (With Contact N)',
      '{activity.assignee_N_checksum_value}' => 'Checksum value (Assignee N)',
      '{activity.source_employer_id.display_name}' => 'Current Employer (Added By)',
      '{activity.target_N_employer_id.display_name}' => 'Current Employer (With Contact N)',
      '{activity.assignee_N_employer_id.display_name}' => 'Current Employer (Assignee N)',
      '{activity.source_address_primary.country_id.region_id:name}' => 'World Region (Added By)',
      '{activity.target_N_address_primary.country_id.region_id:name}' => 'World Region (With Contact N)',
      '{activity.assignee_N_address_primary.country_id.region_id:name}' => 'World Region (Assignee N)',
      '{activity.source_address_primary.country_id:abbr}' => 'Country ISO Code (Added By)',
      '{activity.target_N_address_primary.country_id:abbr}' => 'Country ISO Code (With Contact N)',
      '{activity.assignee_N_address_primary.country_id:abbr}' => 'Country ISO Code (Assignee N)',
      '{activity.source_id}' => 'Contact ID (Added By)',
      '{activity.target_N_id}' => 'Contact ID (With Contact N)',
      '{activity.assignee_N_id}' => 'Contact ID (Assignee N)',
      '{activity.source_contact_type:label}' => 'Contact Type (Added By)',
      '{activity.target_N_contact_type:label}' => 'Contact Type (With Contact N)',
      '{activity.assignee_N_contact_type:label}' => 'Contact Type (Assignee N)',
      '{activity.source_external_identifier}' => 'External Identifier (Added By)',
      '{activity.target_N_external_identifier}' => 'External Identifier (With Contact N)',
      '{activity.assignee_N_external_identifier}' => 'External Identifier (Assignee N)',
      '{activity.source_display_name}' => 'Display Name (Added By)',
      '{activity.target_N_display_name}' => 'Display Name (With Contact N)',
      '{activity.assignee_N_display_name}' => 'Display Name (Assignee N)',
      '{activity.source_first_name}' => 'First Name (Added By)',
      '{activity.target_N_first_name}' => 'First Name (With Contact N)',
      '{activity.assignee_N_first_name}' => 'First Name (Assignee N)',
      '{activity.source_middle_name}' => 'Middle Name (Added By)',
      '{activity.target_N_middle_name}' => 'Middle Name (With Contact N)',
      '{activity.assignee_N_middle_name}' => 'Middle Name (Assignee N)',
      '{activity.source_last_name}' => 'Last Name (Added By)',
      '{activity.target_N_last_name}' => 'Last Name (With Contact N)',
      '{activity.assignee_N_last_name}' => 'Last Name (Assignee N)',
      '{activity.source_do_not_email:label}' => 'Do Not Email (Added By)',
      '{activity.target_N_do_not_email:label}' => 'Do Not Email (With Contact N)',
      '{activity.assignee_N_do_not_email:label}' => 'Do Not Email (Assignee N)',
      '{activity.source_do_not_phone:label}' => 'Do Not Phone (Added By)',
      '{activity.target_N_do_not_phone:label}' => 'Do Not Phone (With Contact N)',
      '{activity.assignee_N_do_not_phone:label}' => 'Do Not Phone (Assignee N)',
      '{activity.source_do_not_mail:label}' => 'Do Not Mail (Added By)',
      '{activity.target_N_do_not_mail:label}' => 'Do Not Mail (With Contact N)',
      '{activity.assignee_N_do_not_mail:label}' => 'Do Not Mail (Assignee N)',
      '{activity.source_do_not_sms:label}' => 'Do Not Sms (Added By)',
      '{activity.target_N_do_not_sms:label}' => 'Do Not Sms (With Contact N)',
      '{activity.assignee_N_do_not_sms:label}' => 'Do Not Sms (Assignee N)',
      '{activity.source_do_not_trade:label}' => 'Do Not Trade (Added By)',
      '{activity.target_N_do_not_trade:label}' => 'Do Not Trade (With Contact N)',
      '{activity.assignee_N_do_not_trade:label}' => 'Do Not Trade (Assignee N)',
      '{activity.source_is_opt_out:label}' => 'No Bulk Emails (User Opt Out) (Added By)',
      '{activity.target_N_is_opt_out:label}' => 'No Bulk Emails (User Opt Out) (With Contact N)',
      '{activity.assignee_N_is_opt_out:label}' => 'No Bulk Emails (User Opt Out) (Assignee N)',
      '{activity.source_sort_name}' => 'Sort Name (Added By)',
      '{activity.target_N_sort_name}' => 'Sort Name (With Contact N)',
      '{activity.assignee_N_sort_name}' => 'Sort Name (Assignee N)',
      '{activity.source_nick_name}' => 'Nickname (Added By)',
      '{activity.target_N_nick_name}' => 'Nickname (With Contact N)',
      '{activity.assignee_N_nick_name}' => 'Nickname (Assignee N)',
      '{activity.source_image_URL}' => 'Image Url (Added By)',
      '{activity.target_N_image_URL}' => 'Image Url (With Contact N)',
      '{activity.assignee_N_image_URL}' => 'Image Url (Assignee N)',
      '{activity.source_preferred_communication_method:label}' => 'Preferred Communication Method (Added By)',
      '{activity.target_N_preferred_communication_method:label}' => 'Preferred Communication Method (With Contact N)',
      '{activity.assignee_N_preferred_communication_method:label}' => 'Preferred Communication Method (Assignee N)',
      '{activity.source_preferred_language:label}' => 'Preferred Language (Added By)',
      '{activity.target_N_preferred_language:label}' => 'Preferred Language (With Contact N)',
      '{activity.assignee_N_preferred_language:label}' => 'Preferred Language (Assignee N)',
      '{activity.source_hash}' => 'Contact Hash (Added By)',
      '{activity.target_N_hash}' => 'Contact Hash (With Contact N)',
      '{activity.assignee_N_hash}' => 'Contact Hash (Assignee N)',
      '{activity.source_source}' => 'Contact Source (Added By)',
      '{activity.target_N_source}' => 'Contact Source (With Contact N)',
      '{activity.assignee_N_source}' => 'Contact Source (Assignee N)',
      '{activity.source_prefix_id:label}' => 'Individual Prefix (Added By)',
      '{activity.target_N_prefix_id:label}' => 'Individual Prefix (With Contact N)',
      '{activity.assignee_N_prefix_id:label}' => 'Individual Prefix (Assignee N)',
      '{activity.source_suffix_id:label}' => 'Individual Suffix (Added By)',
      '{activity.target_N_suffix_id:label}' => 'Individual Suffix (With Contact N)',
      '{activity.assignee_N_suffix_id:label}' => 'Individual Suffix (Assignee N)',
      '{activity.source_formal_title}' => 'Formal Title (Added By)',
      '{activity.target_N_formal_title}' => 'Formal Title (With Contact N)',
      '{activity.assignee_N_formal_title}' => 'Formal Title (Assignee N)',
      '{activity.source_communication_style_id:label}' => 'Communication Style (Added By)',
      '{activity.target_N_communication_style_id:label}' => 'Communication Style (With Contact N)',
      '{activity.assignee_N_communication_style_id:label}' => 'Communication Style (Assignee N)',
      '{activity.source_email_greeting_display}' => 'Email Greeting (Added By)',
      '{activity.target_N_email_greeting_display}' => 'Email Greeting (With Contact N)',
      '{activity.assignee_N_email_greeting_display}' => 'Email Greeting (Assignee N)',
      '{activity.source_postal_greeting_display}' => 'Postal Greeting (Added By)',
      '{activity.target_N_postal_greeting_display}' => 'Postal Greeting (With Contact N)',
      '{activity.assignee_N_postal_greeting_display}' => 'Postal Greeting (Assignee N)',
      '{activity.source_addressee_display}' => 'Addressee (Added By)',
      '{activity.target_N_addressee_display}' => 'Addressee (With Contact N)',
      '{activity.assignee_N_addressee_display}' => 'Addressee (Assignee N)',
      '{activity.source_job_title}' => 'Job Title (Added By)',
      '{activity.target_N_job_title}' => 'Job Title (With Contact N)',
      '{activity.assignee_N_job_title}' => 'Job Title (Assignee N)',
      '{activity.source_gender_id:label}' => 'Gender (Added By)',
      '{activity.target_N_gender_id:label}' => 'Gender (With Contact N)',
      '{activity.assignee_N_gender_id:label}' => 'Gender (Assignee N)',
      '{activity.source_birth_date}' => 'Birth Date (Added By)',
      '{activity.target_N_birth_date}' => 'Birth Date (With Contact N)',
      '{activity.assignee_N_birth_date}' => 'Birth Date (Assignee N)',
      '{activity.source_deceased_date}' => 'Deceased / Closed Date (Added By)',
      '{activity.target_N_deceased_date}' => 'Deceased / Closed Date (With Contact N)',
      '{activity.assignee_N_deceased_date}' => 'Deceased / Closed Date (Assignee N)',
      '{activity.source_employer_id}' => 'Current Employer ID (Added By)',
      '{activity.target_N_employer_id}' => 'Current Employer ID (With Contact N)',
      '{activity.assignee_N_employer_id}' => 'Current Employer ID (Assignee N)',
      '{activity.source_is_deleted:label}' => 'Contact is in Trash (Added By)',
      '{activity.target_N_is_deleted:label}' => 'Contact is in Trash (With Contact N)',
      '{activity.assignee_N_is_deleted:label}' => 'Contact is in Trash (Assignee N)',
      '{activity.source_created_date}' => 'Created Date (Added By)',
      '{activity.target_N_created_date}' => 'Created Date (With Contact N)',
      '{activity.assignee_N_created_date}' => 'Created Date (Assignee N)',
      '{activity.source_modified_date}' => 'Modified Date (Added By)',
      '{activity.target_N_modified_date}' => 'Modified Date (With Contact N)',
      '{activity.assignee_N_modified_date}' => 'Modified Date (Assignee N)',
      '{activity.source_address_primary.id}' => 'Address ID (Added By)',
      '{activity.target_N_address_primary.id}' => 'Address ID (With Contact N)',
      '{activity.assignee_N_address_primary.id}' => 'Address ID (Assignee N)',
      '{activity.source_address_primary.location_type_id:label}' => 'Address Location Type (Added By)',
      '{activity.target_N_address_primary.location_type_id:label}' => 'Address Location Type (With Contact N)',
      '{activity.assignee_N_address_primary.location_type_id:label}' => 'Address Location Type (Assignee N)',
      '{activity.source_address_primary.street_address}' => 'Street Address (Added By)',
      '{activity.target_N_address_primary.street_address}' => 'Street Address (With Contact N)',
      '{activity.assignee_N_address_primary.street_address}' => 'Street Address (Assignee N)',
      '{activity.source_address_primary.street_number}' => 'Street Number (Added By)',
      '{activity.target_N_address_primary.street_number}' => 'Street Number (With Contact N)',
      '{activity.assignee_N_address_primary.street_number}' => 'Street Number (Assignee N)',
      '{activity.source_address_primary.street_number_suffix}' => 'Street Number Suffix (Added By)',
      '{activity.target_N_address_primary.street_number_suffix}' => 'Street Number Suffix (With Contact N)',
      '{activity.assignee_N_address_primary.street_number_suffix}' => 'Street Number Suffix (Assignee N)',
      '{activity.source_address_primary.street_name}' => 'Street Name (Added By)',
      '{activity.target_N_address_primary.street_name}' => 'Street Name (With Contact N)',
      '{activity.assignee_N_address_primary.street_name}' => 'Street Name (Assignee N)',
      '{activity.source_address_primary.street_unit}' => 'Street Unit (Added By)',
      '{activity.target_N_address_primary.street_unit}' => 'Street Unit (With Contact N)',
      '{activity.assignee_N_address_primary.street_unit}' => 'Street Unit (Assignee N)',
      '{activity.source_address_primary.supplemental_address_1}' => 'Supplemental Address 1 (Added By)',
      '{activity.target_N_address_primary.supplemental_address_1}' => 'Supplemental Address 1 (With Contact N)',
      '{activity.assignee_N_address_primary.supplemental_address_1}' => 'Supplemental Address 1 (Assignee N)',
      '{activity.source_address_primary.supplemental_address_2}' => 'Supplemental Address 2 (Added By)',
      '{activity.target_N_address_primary.supplemental_address_2}' => 'Supplemental Address 2 (With Contact N)',
      '{activity.assignee_N_address_primary.supplemental_address_2}' => 'Supplemental Address 2 (Assignee N)',
      '{activity.source_address_primary.supplemental_address_3}' => 'Supplemental Address 3 (Added By)',
      '{activity.target_N_address_primary.supplemental_address_3}' => 'Supplemental Address 3 (With Contact N)',
      '{activity.assignee_N_address_primary.supplemental_address_3}' => 'Supplemental Address 3 (Assignee N)',
      '{activity.source_address_primary.city}' => 'City (Added By)',
      '{activity.target_N_address_primary.city}' => 'City (With Contact N)',
      '{activity.assignee_N_address_primary.city}' => 'City (Assignee N)',
      '{activity.source_address_primary.county_id:label}' => 'County (Added By)',
      '{activity.target_N_address_primary.county_id:label}' => 'County (With Contact N)',
      '{activity.assignee_N_address_primary.county_id:label}' => 'County (Assignee N)',
      '{activity.source_address_primary.postal_code_suffix}' => 'Postal Code Suffix (Added By)',
      '{activity.target_N_address_primary.postal_code_suffix}' => 'Postal Code Suffix (With Contact N)',
      '{activity.assignee_N_address_primary.postal_code_suffix}' => 'Postal Code Suffix (Assignee N)',
      '{activity.source_address_primary.postal_code}' => 'Postal Code (Added By)',
      '{activity.target_N_address_primary.postal_code}' => 'Postal Code (With Contact N)',
      '{activity.assignee_N_address_primary.postal_code}' => 'Postal Code (Assignee N)',
      '{activity.source_address_primary.country_id:label}' => 'Country (Added By)',
      '{activity.target_N_address_primary.country_id:label}' => 'Country (With Contact N)',
      '{activity.assignee_N_address_primary.country_id:label}' => 'Country (Assignee N)',
      '{activity.source_address_primary.geo_code_1}' => 'Latitude (Added By)',
      '{activity.target_N_address_primary.geo_code_1}' => 'Latitude (With Contact N)',
      '{activity.assignee_N_address_primary.geo_code_1}' => 'Latitude (Assignee N)',
      '{activity.source_address_primary.geo_code_2}' => 'Longitude (Added By)',
      '{activity.target_N_address_primary.geo_code_2}' => 'Longitude (With Contact N)',
      '{activity.assignee_N_address_primary.geo_code_2}' => 'Longitude (Assignee N)',
      '{activity.source_address_primary.name}' => 'Address Name (Added By)',
      '{activity.target_N_address_primary.name}' => 'Address Name (With Contact N)',
      '{activity.assignee_N_address_primary.name}' => 'Address Name (Assignee N)',
      '{activity.source_address_primary.master_id}' => 'Master Address ID (Added By)',
      '{activity.target_N_address_primary.master_id}' => 'Master Address ID (With Contact N)',
      '{activity.assignee_N_address_primary.master_id}' => 'Master Address ID (Assignee N)',
      '{activity.source_phone_primary.phone}' => 'Phone (Added By)',
      '{activity.target_N_phone_primary.phone}' => 'Phone (With Contact N)',
      '{activity.assignee_N_phone_primary.phone}' => 'Phone (Assignee N)',
      '{activity.source_phone_primary.phone_ext}' => 'Phone Extension (Added By)',
      '{activity.target_N_phone_primary.phone_ext}' => 'Phone Extension (With Contact N)',
      '{activity.assignee_N_phone_primary.phone_ext}' => 'Phone Extension (Assignee N)',
      '{activity.source_phone_primary.phone_type_id:label}' => 'Phone Type (Added By)',
      '{activity.target_N_phone_primary.phone_type_id:label}' => 'Phone Type (With Contact N)',
      '{activity.assignee_N_phone_primary.phone_type_id:label}' => 'Phone Type (Assignee N)',
      '{activity.source_email_primary.email}' => 'Email (Added By)',
      '{activity.target_N_email_primary.email}' => 'Email (With Contact N)',
      '{activity.assignee_N_email_primary.email}' => 'Email (Assignee N)',
      '{activity.source_email_primary.on_hold:label}' => 'On Hold (Added By)',
      '{activity.target_N_email_primary.on_hold:label}' => 'On Hold (With Contact N)',
      '{activity.assignee_N_email_primary.on_hold:label}' => 'On Hold (Assignee N)',
      '{activity.source_email_primary.signature_text}' => 'Signature Text (Added By)',
      '{activity.target_N_email_primary.signature_text}' => 'Signature Text (With Contact N)',
      '{activity.assignee_N_email_primary.signature_text}' => 'Signature Text (Assignee N)',
      '{activity.source_email_primary.signature_html}' => 'Signature Html (Added By)',
      '{activity.target_N_email_primary.signature_html}' => 'Signature Html (With Contact N)',
      '{activity.assignee_N_email_primary.signature_html}' => 'Signature Html (Assignee N)',
      '{activity.source_website_first.url}' => 'Website (Added By)',
      '{activity.target_N_website_first.url}' => 'Website (With Contact N)',
      '{activity.assignee_N_website_first.url}' => 'Website (Assignee N)',
      '{activity.source_im_primary.name}' => 'IM Screen Name (Added By)',
      '{activity.target_N_im_primary.name}' => 'IM Screen Name (With Contact N)',
      '{activity.assignee_N_im_primary.name}' => 'IM Screen Name (Assignee N)',
      '{activity.source_im_primary.provider_id:label}' => 'IM Provider (Added By)',
      '{activity.target_N_im_primary.provider_id:label}' => 'IM Provider (With Contact N)',
      '{activity.assignee_N_im_primary.provider_id:label}' => 'IM Provider (Assignee N)',
      '{activity.source_address_primary.state_province_id:abbr}' => 'State/Province (Added By)',
      '{activity.target_N_address_primary.state_province_id:abbr}' => 'State/Province (With Contact N)',
      '{activity.assignee_N_address_primary.state_province_id:abbr}' => 'State/Province (Assignee N)',
    ];
  }

  public function testCreateDocumentCustomFieldTokens(): void {
    // Set up custom group, and field
    // returns custom_group_id, custom_field_id, custom_field_option_group_id, custom_field_group_options
    $cg = $this->entityCustomGroupWithSingleStringMultiSelectFieldCreate("MyCustomField", "ActivityTest.php");
    $cf = 'custom_' . $cg['custom_field_id'];
    foreach (array_keys($cg['custom_field_group_options']) as $option) {
      $activity = $this->activityCreate([$cf => $option]);
      $activities[] = [
        'id' => $activity['id'],
        'option' => $option,
      ];
    }

    $html_message = "Custom: {activity.$cf}";
    $activityIds = CRM_Utils_Array::collect('id', $activities);
    $form = $this->getFormObject('CRM_Activity_Form_Task_PDF');
    try {
      $output = $form->createDocument($activityIds, $html_message, []);
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      $output = $e->errorData['html'];
    }
    // Should have one row of output per activity
    $this->assertCount(count($activities), $output);

    // Check each line has the correct substitution
    foreach ($output as $key => $line) {
      $this->assertEquals($line, 'Custom: ' . $cg['custom_field_group_options'][$activities[$key]['option']]);
    }
  }

  /**
   * Unknown tokens are removed at the very end.
   *
   * @throws \CRM_Core_Exception
   */
  public function testCreateDocumentUnknownTokens(): void {
    $activity = $this->activityCreate();
    $html_message = 'Unknown token:{activity.something_unknown}';
    $form = $this->getFormObject('CRM_Activity_Form_Task_PDF', ['document_type' => 'pdf']);
    try {
      $form->createDocument([$activity['id']], $html_message, []);
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      $html = $e->errorData['html'];
      $this->assertStringContainsString('<div id="crm-container">
Unknown token:
    </div>', $html);
      return;
    }
    $this->fail('should be unreachable');
  }

  public function testCreateDocumentSpecialTokens() {
    $activity = $this->activityCreate();
    $data = [
      ["Source First Name: {activity.source_first_name}", "Source First Name: Anthony"],
      ["Source Display Name: {activity.source_display_name}", "Source Display Name: Mr. Anthony Anderson II"],
      ["Target N First Name: {activity.target_N_first_name}", "Target N First Name: Julia"],
      ["Target N Display Name: {activity.target_N_display_name}", "Target N Display Name: Mr. Julia Anderson II"],
      ["Target 0 First Name: {activity.target_0_first_name}", "Target 0 First Name: Julia"],
      ["Target 1 First Name: {activity.target_1_first_name}", "Target 1 First Name: Julia"],
      ["Target 2 First Name: {activity.target_2_first_name}", "Target 2 First Name: "],
      ["Target N Contact ID: {activity.target_N_id}", "Target N Contact ID: {$activity['target_contact_id']}"],
      ["Assignee N First Name: {activity.assignee_N_first_name}", "Assignee N First Name: Julia"],
      ["Assignee N Display Name: {activity.assignee_N_display_name}", "Assignee N Display Name: Mr. Julia Anderson II"],
      ["Assignee 0 First Name: {activity.assignee_0_first_name}", "Assignee 0 First Name: Julia"],
      ["Assignee 1 First Name: {activity.assignee_1_first_name}", "Assignee 1 First Name: Julia"],
      ["Assignee 2 First Name: {activity.assignee_2_first_name}", "Assignee 2 First Name: "],
      ["Assignee N Contact ID: {activity.assignee_N_id}", "Assignee N Contact ID: {$activity['assignee_contact_id']}"],
      ["Assignee Count: {activity.assignees_count}", "Assignee Count: 1"],
      ["Target Count: {activity.targets_count}", "Target Count: 1"],
      ["Assignee Count: {activity.assignee_count}", "Assignee Count: 1"],
      ["Target Count: {activity.target_count}", "Target Count: 1"],
    ];
    $html_message = implode("\n", CRM_Utils_Array::collect('0', $data)) . "\n";

    $tp = new TokenProcessor(\Civi::dispatcher(), [
      'controller' => self::class,
      'smarty' => FALSE,
      'schema' => ['activityId'],
    ]);
    $tp->addMessage('body_html', $html_message, 'text/html');
    $tp->addRow()->context('activityId', $activity['id']);
    $tp->evaluate();

    $html = [];
    foreach ($tp->getRows() as $row) {
      $html[] = $row->render('body_html');
    }
    $output = implode(CRM_Utils_String::LINEFEED, $html);

    foreach ($data as $line) {
      $this->assertStringContainsString($line[1], $output);
    }
  }

}
