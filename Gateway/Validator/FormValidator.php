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
        $isValid = false;
        $errors = ['Hubo un error'];

        return $this->createResult($isValid, $errors);
    }
}
