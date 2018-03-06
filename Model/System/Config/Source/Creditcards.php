<?php

namespace EPG\EasyPaymentGateway\Model\System\Config\Source;

class Creditcards
{
    const CHANNEL_VISA = 'visa';
    const CHANNEL_MASTER_CARD = 'master_card';
    const CHANNEL_MAESTRO = 'maestro';
    const CHANNEL_AMERICAN_EXPRESS = 'american_express';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::CHANNEL_VISA, 'label' => __('Visa')],
            ['value' => self::CHANNEL_MASTER_CARD, 'label' => __('Mastercard')],
            ['value' => self::CHANNEL_MAESTRO, 'label' => __('Maestro')],
            ['value' => self::CHANNEL_AMERICAN_EXPRESS, 'label' => __('American Express')]
        ];
    }
}
