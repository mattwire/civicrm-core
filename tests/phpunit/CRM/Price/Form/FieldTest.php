<?php

class CRM_Price_Form_FieldTest extends CiviUnitTestCase {

  protected $priceFieldValues;
  protected $visibilityOptionsKeys;
  protected $visibilityOptions;
  protected $publicFieldParams;
  protected $adminFieldParams;

  public function setUp(): void {
    parent::setUp();

    $this->visibilityOptionsKeys = CRM_Core_PseudoConstant::get('CRM_Price_BAO_PriceFieldValue', 'visibility_id', [
      'labelColumn' => 'name',
      'flip' => TRUE,
    ]);

    $this->publicFieldParams = $this->initializeFieldParameters([
      'label' => 'Public Price Field',
      'name' => 'public_price',
      'visibility_id' => $this->visibilityOptionsKeys['public'],
    ]);

    $this->adminFieldParams = $this->initializeFieldParameters([
      'label' => 'Public Price Field',
      'name' => 'public_price',
      'visibility_id' => $this->visibilityOptionsKeys['admin'],
    ]);
  }

  public function testPublicFieldWithOnlyAdminOptionsIsNotAllowed(): void {
    $this->publicFieldParams['option_label'][1] = 'Admin Price';
    $this->publicFieldParams['option_amount'][1] = 10;
    $this->publicFieldParams['option_visibility_id'][1] = $this->visibilityOptionsKeys['admin'];

    $form = new CRM_Price_Form_Field();
    $form->_action = CRM_Core_Action::ADD;
    $files = [];

    $validationResult = $form->formRule($this->publicFieldParams, $files, $form);
    $this->assertIsArray($validationResult);
    $this->assertTrue(array_key_exists('visibility_id', $validationResult));
  }

  public function testAdminFieldDoesNotAllowPublicOptions(): void {
    $this->adminFieldParams['option_label'][1] = 'Admin Price';
    $this->adminFieldParams['option_amount'][1] = 10;
    $this->adminFieldParams['option_visibility_id'][1] = $this->visibilityOptionsKeys['public'];

    $form = new CRM_Price_Form_Field();
    $form->_action = CRM_Core_Action::ADD;
    $files = [];

    $validationResult = $form->formRule($this->adminFieldParams, $files, $form);
    $this->assertIsArray($validationResult);
    $this->assertTrue(array_key_exists('visibility_id', $validationResult));
  }

  public function testFieldMetadata(): void {
    $fields = CRM_Price_DAO_PriceFieldValue::fields();
    $this->assertSame([18, 9], $fields['amount']['precision']);
    $this->assertSame(CRM_Utils_Type::T_MONEY, $fields['amount']['type']);
    $this->assertArrayNotHasKey('maxlength', $fields['amount']);
  }

  /**
   * Test submitting a large float value is stored correctly in the db.
   *
   * @param string $thousandSeparator
   *   punctuation used to refer to thousands.
   *
   * @dataProvider getThousandSeparators
   */
  public function testLargeFloatOptionValue($thousandSeparator) {
    $this->setCurrencySeparators($thousandSeparator);
    $thousands = Civi::settings()->get('monetaryThousandSeparator');
    $decimal = Civi::settings()->get('monetaryDecimalPoint');
    $paramsSet['title'] = 'Price Set' . bin2hex(random_bytes(4));
    $paramsSet['name'] = CRM_Utils_String::titleToVar($paramsSet['title']);
    $paramsSet['is_active'] = TRUE;
    $paramsSet['financial_type_id'] = 'Event Fee';
    $paramsSet['extends'] = 1;
    $priceSet = $this->callAPISuccess('price_set', 'create', $paramsSet);
    $form = new CRM_Price_Form_Field();
    $form->_action = CRM_Core_Action::ADD;
    $form->setPriceSetId($priceSet['id']);
    $this->publicFieldParams['option_label'][1] = 'Large Float';
    $this->publicFieldParams['option_amount'][1] = '123' . $thousands . '456' . $thousands . '789' . $decimal . '987654321';
    $this->publicFieldParams['option_visibility_id'][1] = $this->visibilityOptionsKeys['public'];
    $priceField = $form->submit($this->publicFieldParams);
    $priceOptions = $this->callAPISuccess('PriceFieldValue', 'get', ['price_field_id' => $priceField->id]);
    $this->assertEquals(123456789.987654321, $priceOptions['values'][$priceOptions['id']]['amount']);
  }

  private function initializeFieldParameters($params) {
    $defaultParams = [
      'label' => 'Price Field',
      'name' => CRM_Utils_String::titleToVar('Price Field'),
      'html_type' => 'Select',
      'is_display_amounts' => 1,
      'weight' => 1,
      'options_per_line' => 1,
      'is_enter_qty' => 1,
      'financial_type_id' => $this->getFinancialTypeID('Event Fee'),
      'visibility_id' => $this->visibilityOptionsKeys['public'],
      'price' => 10,
      'active_on' => date('Y-m-d'),
      'expire_on' => '',
    ];

    for ($index = 1; $index <= CRM_Price_Form_Field::NUM_OPTION; $index++) {
      $defaultParams['option_label'][$index] = NULL;
      $defaultParams['option_value'][$index] = NULL;
      $defaultParams['option_name'][$index] = NULL;
      $defaultParams['option_weight'][$index] = NULL;
      $defaultParams['option_amount'][$index] = NULL;
      $defaultParams['option_visibility_id'][$index] = NULL;
    }

    return array_merge($defaultParams, $params);
  }

  /**
   * Test end date not allowed with only 'time' part.
   */
  public function testEndDateWithoutDateNotAllowed(): void {
    $form = new CRM_Price_Form_Field();
    $form->_action = CRM_Core_Action::ADD;
    $values = $this->initializeFieldParameters([
      'expire_on' => '00:01',
    ]);
    $validationResult = \CRM_Price_Form_Field::formRule($values, [], $form);
    $this->assertArrayHasKey('expire_on', $validationResult);
  }

  /**
   * Test end date must be after start date.
   */
  public function testEndDateBeforeStartDateNotAllowed(): void {
    $form = new CRM_Price_Form_Field();
    $form->_action = CRM_Core_Action::ADD;
    $values = $this->initializeFieldParameters([
      'expire_on' => '1900-01-01 00:00',
    ]);
    $validationResult = \CRM_Price_Form_Field::formRule($values, [], $form);
    $this->assertArrayHasKey('expire_on', $validationResult);
  }

}
