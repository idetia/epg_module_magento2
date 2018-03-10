<?php

namespace EPG\EasyPaymentGateway\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class FormValidator extends AbstractValidator
{
    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $errors = [];

        return $this->createResult($isValid, $errors);
    }
}
