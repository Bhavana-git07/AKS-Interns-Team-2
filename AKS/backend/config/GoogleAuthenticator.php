<?php
// config/GoogleAuthenticator.php
// Pure-PHP, zero-dependency helper class to generate and verify TOTP codes.

class GoogleAuthenticator {
    /**
     * Generates a 16-character base32 secret key.
     */
    public function createSecret($secretLength = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Calculates the TOTP code for a given secret and timeslice.
     */
    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretkey = $this->base32Decode($secret);
        if ($secretkey === false) {
            return false;
        }

        // Pack timeslice into 8-byte binary string
        $time = chr(0).chr(0).chr(0).chr(0).pack('N', $timeSlice);
        
        // HMAC-SHA1
        $hm = hash_hmac('sha1', $time, $secretkey, true);
        
        // Dynamic Truncation
        $offset = ord($hm[19]) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        $modulo = 10 ** 6;
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifies the submitted TOTP code against the secret key.
     * Allows a discrepancy of ±1 time slice (30 seconds) to account for time drift.
     */
    public function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Helper function to decode a base32 string to standard binary data.
     */
    private function base32Decode($base32) {
        if (empty($base32)) return '';
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $base32 = strtoupper(str_replace('=', '', $base32));
        $base32 = str_split($base32);
        
        $binaryString = "";
        $buffer = 0;
        $bitsLeft = 0;
        
        foreach ($base32 as $char) {
            if (!isset($base32charsFlipped[$char])) {
                return false; // Invalid base32 character
            }
            
            $val = $base32charsFlipped[$char];
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binaryString .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        
        return $binaryString;
    }
}
?>
