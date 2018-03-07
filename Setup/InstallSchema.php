<?php

namespace EPG\EasyPaymentGateway\Setup;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{

	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$installer = $setup;
		$installer->startSetup();

        /**
         * Create table 'epg/order'
         */
		if (!$installer->tableExists('easypaymentgateway_order')) {

            $orderTable = $installer->getConnection()
                ->newTable($installer->getTable('easypaymentgateway_order'))
                ->addColumn('id_epg_order', Table::TYPE_INTEGER, null, array(
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true
                ), 'EPG Order Id')
                ->addColumn('id_order', Table::TYPE_INTEGER, 11, array(
                    'nullable'  => true
                ), 'Order Id')
                ->addColumn('id_cart', Table::TYPE_INTEGER, 11, array(
                    'nullable'  => true
                ), 'Cart Id')
                ->addColumn('id_account', Table::TYPE_TEXT, 255, array(
                    'nullable'  => true
                ), 'Account Id')
                ->addColumn('id_transaction', Table::TYPE_TEXT, 64, array(
                    'nullable'  => true
                ), 'Transaction Id')
                ->addColumn('total_paid', Table::TYPE_DECIMAL, '20,6', array(
                    'nullable'  => false,
                    'default'   => '0.000000',
                ), 'Total paid')
                ->addColumn('payment_status', Table::TYPE_TEXT, 255, array(
                    'nullable'  => true
                ), 'Payment status')
                ->addColumn('epg_customer_id', Table::TYPE_TEXT, 255, array(
                    'nullable'  => false
                ), 'EPG Customer Id')
                ->addColumn('token', Table::TYPE_TEXT, 255, array(
                    'nullable'  => false
                ), 'Token')
                ->addColumn('cancel_token', Table::TYPE_TEXT, 255, array(
                    'nullable'  => false
                ), 'Cancel Token')
                ->addColumn('error_token', Table::TYPE_TEXT, 255, array(
                    'nullable'  => false
                ), 'Error Token')
                ->addColumn('payment_details', Table::TYPE_TEXT, '64K', array(
                    'nullable'  => false
                ), 'Payment details')
                ->addColumn('create_at', Table::TYPE_TIMESTAMP, null, array(
                ), 'Create at')
                ->addColumn('update_at', Table::TYPE_TIMESTAMP, null, array(
                ), 'Update at')
                ->setComment('EPG Order Table');
            $installer->getConnection()->createTable($orderTable);
		}

        /**
         * Create table 'epg/customer'
         */
         if (!$installer->tableExists('easypaymentgateway_customer')) {
            $customerTable = $installer->getConnection()
                ->newTable($installer->getTable('easypaymentgateway_customer'))
                ->addColumn('id', Table::TYPE_INTEGER, null, array(
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true,
                ), 'Id')
                ->addColumn('customer_id', Table::TYPE_INTEGER, 11, array(
                    'nullable'  => false
                ), 'Customer Id')
                ->addColumn('epg_customer_id', Table::TYPE_TEXT, 255, array(
                    'nullable'  => false
                ), 'EPG Customer Id')
                ->addColumn('accounts', Table::TYPE_TEXT, '64K', array(
                    'nullable'  => false
                ), 'Accounts')
                ->addColumn('create_at', Table::TYPE_TIMESTAMP, null, array(
                ), 'Create at')
                ->addColumn('update_at', Table::TYPE_TIMESTAMP, null, array(
                ), 'Update at')
                ->setComment('EPG Customer Table');
            $installer->getConnection()->createTable($customerTable);
        }

        if ($installer->getConnection()->tableColumnExists('sales_order', 'epg_transaction_id') === false) {
            $installer->getConnection()->addColumn($installer->getTable('sales_order'), 'epg_transaction_id', array(
                'type'      => Table::TYPE_TEXT,
                'nullable'  => true,
                'length'    => 255,
                'after'     => null,
                'comment'   => 'EPG Transaction Id'
                ));
        }

        if ($installer->getConnection()->tableColumnExists('sales_order_grid', 'epg_transaction_id') === false) {
            $installer->getConnection()->addColumn($installer->getTable('sales_order_grid'), 'epg_transaction_id', array(
                'type'      => Table::TYPE_TEXT,
                'nullable'  => true,
                'length'    => 255,
                'after'     => null,
                'comment'   => 'EPG Transaction Id'
                ));
        }

		$installer->endSetup();
	}
}
