<?php

namespace EPG\EasyPaymentGateway\Model;

class Form
{
    private $paymentMethod;

    public function __construct($paymentMethod)
    {
      $this->paymentMethod = $paymentMethod;
    }

    public function html()
    {
      $result = '';

      $items = $this->paymentMethod['formTemplate']['items'];

      foreach($items as $item) {
        if ($item['info']) {
          continue;
        }
        $result .=  $this->buildField($item);
      }

      return $result;
    }

    private function buildField($item)
    {
      $result = '';

      switch($item['type']) {
          case 'TEXT':
          case 'PASSWORD':
          case 'HIDDEN':
          case 'CVV':

            $type = strtolower($item['type']);
            if ($item['type'] == 'CVV') {
              $type = 'text';
            }

            // Select type
            if (isset($item['values']) && count($item['values']) > 1) {
                $result .= '<select '. ($item['required']?'required':'') .' id="'.$item['name'].'" name="'.$item['name'].'" class="input-text '. $this->htmlClass($item) .'" '. $this->htmlValidators($item['validators']) .' >';
                foreach ($item['values'] as $value) {
                    $result .= '<option value="'. $value . '">'. $value .'</option>';
                }
                $result .= '</select>';

            // Input type
            } else {
                $result .= '<input type="'.$type.'" '. ($item['required']?'required':'') .' id="'.$item['name'].'" name="'.$item['name'].'" value="'.$item['defValue'].'" placeholder="'.$item['placeHolder'].'" class="input-text '. $this->htmlClass($item) .'" '. $this->htmlValidators($item['validators']) .' />';
            }

            if ($type == 'hidden') {
              return $result;
            }

            break;
          case 'EXP_DATE':

            $months = $this->getMonths();
            $years = $this->getYears();
            $result .= '
                  <div class="v-fix selection '.$item['name'].'Month">
                      <select '. ($item['required']?'required':'') .' id="'.$item['name'].'Month" name="'.$item['name'].'Month" class="month '. $this->htmlClass($item) .'">
                      ';

                      foreach ($months as $v) {
                          $result .= '<option value="'. $v . '">'. $v .'</option>';
                      }

            $result .= '
                      </select>
                  </div>
                  <div class="v-fix selection '.$item['name'].'Year">
                      <select '. ($item['required']?'required':'') .' id="'.$item['name'].'Year" name="'.$item['name'].'Year" class="year '. $this->htmlClass($item) .'">
                      ';

                      foreach ($years as $v) {
                          $result .= '<option value="'. $v . '">'. $v .'</option>';
                      }

            $result .= '
                      </select>
                  </div>
                  ';

            break;
          case 'RADIO':
            $result .= '<input type="radio" '. ($item['checked']?'checked':'') .' id="'.$item['name'].'" name="'.$item['name'].'" value="'.$item['defValue'].'" class="input-radio '. $this->htmlClass($item) .'" '. ($this->htmlValidators($item['validators'])) .' /> ' . $item['defValue'];
            break;
      }

      $label = '<label for="'.$item['name'].'">' . __($item['name'], 'woocommerce-gateway-epg') . ($item['required']?(' <abbr class="required" title="'. __('required', 'woocommerce-gateway-epg') . '">*</abbr>'):'') . '</label>';
      $result = '
              <div class="row form-row form-row-wide '. ($item['required']?'validate-required':'') .'">
                  ' . $label . '
                  ' . $result . '
              </div>';

      return $result;
    }

    private function htmlValidators($validators)
    {
      $result = '';

      foreach($validators as $validator) {
          switch($validator['type']) {
              case 'MIN_LENGTH':
                $validator['type'] = 'minlength';
                $result .= ' ' . strtolower($validator['type']) . '="' . $validator['value'] . '" ';
                break;
              case 'MAX_LENGTH':
                $validator['type'] = 'maxlength';
                $result .= ' ' . strtolower($validator['type']) . '="' . $validator['value'] . '" ';
                break;
              case 'REGEX':
                $validator['type'] = 'pattern';
                $result .= ' ' . strtolower($validator['type']) . '="' . $validator['value'] . '" ';
                break;
              case 'LUHN_CHECK':
              case 'EXPIRY_CHECK':
                break;
          }
      }

      return $result;
    }

    private function htmlClass($item)
    {
      $result = ' ' . $item['name'] . ' ';

      if ($item['internal']) {
        $result .= ' is-internal ';
      } else {
        $result .= ' no-internal ';
      }

      return $result;
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    private function getMonths()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }
        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    private function getYears()
    {
        return range(date('y'), date('y') + 10);
    }

    /**
    * Validate payment form
    */
    public function validate($params, $account = null)
    {
        $result = ['errors' => []];

        $items = $this->paymentMethod['formTemplate']['items'];

        foreach($items as $item) {
          if (!empty($account) && $item['internal']) {
            continue;
          }

          $field = isset($params[$item['name']])?$params[$item['name']]:null;
          if ($item['type'] == 'EXP_DATE') {
              $field = isset($params[$item['name'].'Month'])?$params[$item['name'].'Month']:'';
              $field .= isset($params[$item['name'].'Year'])?$params[$item['name'].'Year']:'';
          }

          // Required
          if (!$item['info'] && $item['required'] && empty($field)) {
              $result['errors'][] = '"' . __($item['name'], 'woocommerce-gateway-epg') . '" ' . __('is_required', 'woocommerce-gateway-epg');
          }

          // Validators
          $errors = $this->checkValidators($item, $field);
          if (!empty($errors)) {
              foreach ($errors as $error) {
                  $result['errors'][] = $error;
              }
          }

        }

        return $result;
    }

    private function checkValidators($item, $field)
    {
        $errors = [];

        if (empty($field)) {
            return null;
        }

        foreach($item['validators'] as $validator) {
            switch($validator['type']) {
                case 'MIN_LENGTH':
                    if (strlen((string)$field) < (int)$validator['value']) {
                        $errors[] = sprintf(__($validator['errorLabel'] . ' %1$s %2$s', 'woocommerce-gateway-epg'),
                                            __($item['name'], 'woocommerce-gateway-epg'),
                                            $validator['errorParams'][0]
                                            );
                    }
                    break;
                case 'MAX_LENGTH':
                    if (strlen((string)$field) > (int)$validator['value']) {
                        $errors[] = sprintf(__($validator['errorLabel'] . ' %1$s %2$s', 'woocommerce-gateway-epg'),
                                            __($item['name'], 'woocommerce-gateway-epg'),
                                            $validator['errorParams'][0]
                                            );
                    }
                    break;
                case 'REGEX':
                    if (!@preg_match('/'.$validator['value'].'/', (string)$field)) {
                        $errors[] = sprintf(__($validator['errorLabel'] . ' %1$s', 'woocommerce-gateway-epg'),
                                            __($item['name'], 'woocommerce-gateway-epg')
                                            );
                    }
                    break;
                case 'LUHN_CHECK':
                    if (empty(self::checkCard($field, true))) {
                        $errors[] = sprintf(__('card_check %1$s', 'woocommerce-gateway-epg'),
                                            __($item['name'], 'woocommerce-gateway-epg')
                                            );
                    }
                    break;
                case 'EXPIRY_CHECK':
                    $error = sprintf(__('date_expired %1$s', 'woocommerce-gateway-epg'),
                                     __($item['name'], 'woocommerce-gateway-epg')
                                     );

                    if (strlen((string)$field) != 4) {
                        $errors[] = $error;
                        break;
                    }

                    try{
                        $date = \DateTime::createFromFormat('y-m-d', substr((string)$field, 2, 4) . '-'. substr((string)$field, 0, 2) . '-01');
                        $date = $date->modify('last day of this month');
                        if ($date < new \DateTime()) {
                            $errors[] = $error;
                        }
                    } catch(\Exception $e){
                        $errors[] = $error;
                    }
                    break;
            }
        }

        return $errors;
    }

    private static final function checkCard($number, $extraCheck = false)
    {
        $cards = array(
            "visa" => "(4\d{12}(?:\d{3})?)",
            "amex" => "(3[47]\d{13})",
            "maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
            "mastercard" => "(5[1-5]\d{14})"
        );

        $names = array("Visa", "American Express", "Maestro", "Mastercard");
        $matches = array();
        $pattern = "#^(?:".implode("|", $cards).")$#";
        $result = preg_match($pattern, str_replace(" ", "", $number), $matches);

        if($extraCheck && $result > 0){
            $result = (self::luhnValidation($number))?1:0;
        }

        return ($result>0)?$names[sizeof($matches)-2]:false;
    }

    private static final function luhnValidation($number)
    {
        settype($number, 'string');
        $number = preg_replace("/[^0-9]/", "", $number);
        $numberChecksum= '';

        $reversedNumberArray = str_split(strrev($number));
        foreach ($reversedNumberArray as $i => $d) {
            $numberChecksum.= (($i % 2) !== 0) ? (string)((int)$d * 2) : $d;
        }

        $sum = array_sum(str_split($numberChecksum));
        return ($sum % 10) === 0;
    }

    /**
    * Return all form fields with its values
    */
    public function fields($params, $account = null)
    {
        $result = [];

        $items = $this->paymentMethod['formTemplate']['items'];

        foreach($items as $item) {
          if (!empty($account) && $item['internal']) {
            continue;
          }

          if ($item['info']) {
              continue;
          }

          $field = isset($params[$item['name']])?$params[$item['name']]:null;
          if ($item['type'] == 'EXP_DATE') {
              $field = isset($params[$item['name'].'Month'])?$params[$item['name'].'Month']:'';
              $field .= isset($params[$item['name'].'Year'])?$params[$item['name'].'Year']:'';
          }

          $result[$item['name']] = $field;
        }

        return $result;
    }
}
