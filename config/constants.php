<?php

// TAX Percentage
define('TAX_PERCENTAGE', 0.15);

// User Types
define('USER_TYPE_ADMIN', 1);
define('USER_TYPE_BUSINESS_OWNER', 2);
define('USER_TYPE_EMPLOYEE', 3);
define('USER_TYPE_ADMIN_STAFF', 4);
define('USER_TYPE_SUPER_ADMIN', 5);
define('USER_TYPE_REFERRAL', 6);
define('USER_TYPE_RESELLER', 7);
// Reseller Constants
define('RESELLER_STATUS_ACTIVE', 1);
define('RESELLER_STATUS_KYC', 2);
define('RESELLER_STATUS_REJECTED', 3);
define('RESELLER_STATUS_INREVIEW', 4);
define('RESELLER_STATUS_BLOCKED', 99);
// TRUE/FALSE
define('BOOLEAN_FALSE', 0);
define('BOOLEAN_TRUE', 1);

// TRANSACTION_TYPES
define('TRANSACTION_TYPE_CASH', 1);
define('TRANSACTION_TYPE_MADA', 2);
define('TRANSACTION_TYPE_STC', 3);
define('TRANSACTION_TYPE_CREDIT', 4);
define('TRANSACTION_TYPE_MULTIPAYMENT', 5);

// SUBSCRIPTION_PLANS
define('SUBSCRIPTION_PLAN_MONTHLY', 1);
define('SUBSCRIPTION_PLAN_QUARTERLY', 2);
define('SUBSCRIPTION_PLAN_YEARLY', 3);

// SUBSCRIPTION_PRICES
define('SUBSCRIPTION_CHARGES_MONTHLY', 50);
define('SUBSCRIPTION_CHARGES_YEARLY', 500);

// REFUND_TYPE
define('REFUND_TYPE_FULL', 1);
define('REFUND_TYPE_PARTIAL', 2);

// Plan Types
define('PLAN_TYPE_BASIC', 1);
define('PLAN_TYPE_PRO', 2);
define('PLAN_TYPE_DAILY', 3);

// Plan Period
define('PERIOD_MONTHLY', 1);
define('PERIOD_YEARLY', 2);
define('PERIOD_DAILY', 3);

// Addon Billing Cycle
define('ADDON_BILLING_MONTHLY', 1);
define('ADDON_BILLING_YEARLY', 2);
define('ADDON_BILLING_DAILY', 3);

// Company Status
define('COMPANY_STATUS_ACTIVE', 1);
define('COMPANY_STATUS_KYC', 2);
define('COMPANY_STATUS_REVIEW', 3);
define('COMPANY_STATUS_SUBSCRIPTION_ENDED', 4);
define('COMPANY_STATUS_SUBSCRIPTION_IN_REVIEW', 5);
define('COMPANY_STATUS_SUBSCRIPTION_INVOICE_GENERATED', 6);
define('COMPANY_STATUS_BLOCKED', 99);

// Invoice Status
define('INVOICE_STATUS_UNPAID', 0);
define('INVOICE_STATUS_CANCELLED', 1);
define('INVOICE_STATUS_PAID', 2);
define('INVOICE_STATUS_REFUNDED', 3);
// When invoice is not paid and has stcpay reference id
define('INVOICE_RECHARGE_REQUEST', 7);
// Sale Invoice Status
define('SALE_INVOICE_STATUS_DRAFT', 1);
define('SALE_INVOICE_STATUS_ISSUE', 2);
define('SALE_INVOICE_STATUS_PARTIALPAID', 3);
define('SALE_INVOICE_STATUS_PAID', 4);
define('SALE_INVOICE_STATUS_CANCELLED', 5);

// Invoice Detail Type
define('INVOICE_DETAIL_TYPE_SUBSCRIPTION', 1);
define('INVOICE_DETAIL_TYPE_LICENSE', 2);
define('INVOICE_DETAIL_TYPE_DISCOUNT', 3);
define('INVOICE_DETAIL_TYPE_TAX', 4);
define('INVOICE_DETAIL_TYPE_DEVICE_PAYMENT', 5);
define('INVOICE_DETAIL_TYPE_ADDON', 6);
define('INVOICE_DETAIL_TYPE_BALANCE_TOPUP', 7);

// Invoice Type
define('INVOICE_TYPE_SUBSCRIPTION', 1);
define('INVOICE_TYPE_LICENSE', 2);
define('INVOICE_TYPE_DEVICE_PAYMENT', 3);
define('INVOICE_TYPE_ADDON', 4);
define('INVOICE_TYPE_BALANCE_TOPUP', 5);

// Payment Brand
define('PAYMENT_BRAND_VISA', 1);
define('PAYMENT_BRAND_MASTER', 2);
define('PAYMENT_BRAND_MADA', 3);
define('PAYMENT_BRAND_STCPAY', 4);

// Payment Status
define('PAYMENT_STATUS_UNPAID', 0);
define('PAYMENT_STATUS_PAID', 1);
define('PAYMENT_STATUS_DECLINE', 2);

// FCM Notification Type
define('FCM_TYPE_NORMAL', 1);
define('FCM_TYPE_RELOAD', 2);

// Device Purchasing amount
define('DEVICE_PURCHASING_AMOUNT', 1000);
define('DEVICE_INSTALLMENT_AMOUNT', 200);

// DEFAULT_COUNTRY
define('COUNTRY_SAUDI_ARABIA', 1);

// Helpdesk Tickets Statuses
define('HELPDESK_TICKET_CREATED', 1);
define('HELPDESK_TICKET_IN_PROGRESS', 2);
define('HELPDESK_TICKET_DONE', 3);
define('HELPDESK_TICKET_CLOSED', 4);

// Per page records
define('PER_PAGE_RECORDS', 10);
define('PER_PAGE_RECORDS_SHORT', 5);

// Odoo Products Reference Numbers
// Additional Users with Balance Months Subscription
define('ODOO_BALANCE_MONTHS_SUB', 'PAMS20');
// PDA Terminal N5 - WISEASY
define('ODOO_PDA_WISEASY', 'POSM01');
// POS Application - Annual Subscription Fee
define('ODOO_ANNUAL_SUB', 'PAAS01');
// POS Application - Monthly Subscription Fee
define('ODOO_MONTHLY_SUB', 'PAMS01');
// PDA Terminal N5 - WISEASY Installment Plan
define('ODOO_PDA_WISEASY_INSTALLMENT', 'POSM02');

// CR & VAT files size in MBs
define('CR_VAT_FILE_SIZE', 10);
// Logo image size in MBs
define('LOGO_SIZE', 2);

// Helpdesk Tickets Statuses
define('ACTIVITY_CREATED', 1);
define('ACTIVITY_DONE', 2);

// Comany will be considered idle if last_active_at date is x days older
define('IDLE_CUSTOMER_DAYS', 14);

//Stock Transfer Status
define('INVENTORY_REQUEST_PENDING', 0);
define('INVENTORY_REQUEST_COMPLETED', 1);
define('INVENTORY_REQUEST_CANCELLED', 2);
define('INVENTORY_REQUEST_REJECTED', 3);
