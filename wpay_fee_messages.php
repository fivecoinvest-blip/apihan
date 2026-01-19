<?php
/**
 * Fee Messaging Helper
 * Displays user-friendly fee information
 */

function getDepositFeeMessage() {
    if (WPAY_CHARGE_DEPOSIT_FEE_TO_USER) {
        return '⚠️ Collection fee (1.6%) will be deducted from your deposit';
    } else {
        return '✅ NO FEES - Deposit with no charges!';
    }
}

function getWithdrawalFeeMessage() {
    if (WPAY_CHARGE_WITHDRAWAL_FEE_TO_USER) {
        $totalFee = (WPAY_PROCESSING_FEE + 1.6); // Estimated with 1.6%
        return "⚠️ Withdrawal fees (1.6% collection + ₱" . WPAY_PROCESSING_FEE . " processing) will be deducted";
    } else {
        return '✅ NO FEES - Withdraw with no charges!';
    }
}

function getAdminFeeStatus() {
    $depositStatus = WPAY_CHARGE_DEPOSIT_FEE_TO_USER ? 'Charging to Users' : 'Admin Covers';
    $withdrawalStatus = WPAY_CHARGE_WITHDRAWAL_FEE_TO_USER ? 'Charging to Users' : 'Admin Covers';
    
    return [
        'deposit' => $depositStatus,
        'withdrawal' => $withdrawalStatus
    ];
}
