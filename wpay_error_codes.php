<?php
/**
 * WPay Error Codes Reference
 * Based on OKExPay API Documentation
 * Source: Provider API Reference
 */

// API Response Codes (from initial request)
function getWPayErrorMessage($code) {
    $errorMessages = [
        0 => 'Success',
        1 => 'Fail',
        2 => 'Merchant ID error',
        3 => 'Account does not exist',
        4 => 'Account status abnormal',
        5 => 'Signature error',
        6 => 'The order already exists',
        7 => 'Order does not exist',
        8 => 'Insufficient permissions',
        9 => 'Insufficient balance',
        10 => 'Incorrect amount',
        11 => 'Channel under maintenance',
        12 => 'Currency does not exist',
        13 => 'The channel does not exist',
        15 => 'Channel failure',
        16 => 'IP address incorrect. Please contact customer service to add it to the whitelist',
        17 => 'The bank does not exist'
    ];
    
    return $errorMessages[$code] ?? 'Unknown error (code: ' . $code . ')';
}

// Order Status Codes (from callback/query)
function getWPayOrderStatus($status) {
    $statusMessages = [
        0 => 'Pending payment',
        1 => 'Payment successful',
        2 => 'Payment failed'
    ];
    
    return $statusMessages[$status] ?? 'Unknown status';
}

// User-friendly error messages
function getWPayUserMessage($code) {
    switch ($code) {
        case 0:
            return 'Transaction initiated successfully';
        case 1:
            return 'Transaction failed. Please try again or contact support.';
        case 2:
            return 'Merchant configuration error. Please contact support.';
        case 3:
            return 'Payment account not found. Please check your account details.';
        case 4:
            return 'Payment account is inactive. Please contact support.';
        case 5:
            return 'Security verification failed. Please try again.';
        case 6:
            return 'This transaction already exists. Please check your transaction history.';
        case 7:
            return 'Order not found in system. Please contact support.';
        case 8:
            return 'You do not have permission to perform this action. Please contact support.';
        case 9:
            return 'Insufficient balance. Please deposit first.';
        case 10:
            return 'Invalid amount. Please check minimum and maximum limits.';
        case 11:
            return 'Payment method is under maintenance. Please try another method or try again later.';
        case 12:
            return 'Currency not supported. Please contact support.';
        case 13:
            return 'Payment channel not available. Please try another method.';
        case 15:
            return 'Payment service temporarily unavailable. Please try again later.';
        case 16:
            return 'Server connection issue. Please contact support with your IP address.';
        case 17:
            return 'Bank not supported. Please try another bank or payment method.';
        default:
            return 'Transaction failed. Please try again or contact support.';
    }
}

// Check if code indicates success
function isWPaySuccess($code) {
    return $code === 0 || $code === '0';
}

// Check if order status is completed
function isWPayOrderCompleted($status) {
    return $status === 1 || $status === '1';
}

// Check if order status is failed
function isWPayOrderFailed($status) {
    return $status === 2 || $status === '2';
}

