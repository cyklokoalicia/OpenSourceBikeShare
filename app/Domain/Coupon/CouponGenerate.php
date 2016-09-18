<?php
namespace BikeShare\Domain\Coupon;

use Exception;

/**
 * Class to handle coupon operations
 * Changes by Alex Rabinovich
 *
 * @author Joash Pereira
 * @date  2015-06-05
 */
class CouponGenerate
{

    /**
     * Number of parts of the code.
     *
     * @var integer
     */
    protected $_parts = 3;

    /**
     * Length of each part.
     *
     * @var integer
     */
    protected $_partLength = 4;

    /**
     * Alphabet used when generating codes. Already leaves
     * easy to confuse letters out.
     *
     * @var array
     */
    protected $_symbols = [
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'J',
        'K',
        'L',
        'M',
        'N',
        'P',
        'Q',
        'R',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y'
    ];

    /**
     * ROT13 encoded list of bad words.
     *
     * @var array
     */
    protected $_badWords = [
        'SHPX',
        'PHAG',
        'JNAX',
        'JNAT',
        'CVFF',
        'PBPX',
        'FUVG',
        'GJNG',
        'GVGF',
        'SNEG',
        'URYY',
        'ZHSS',
        'QVPX',
        'XABO',
        'NEFR',
        'FUNT',
        'GBFF',
        'FYHG',
        'GHEQ',
        'FYNT',
        'PENC',
        'CBBC',
        'OHGG',
        'SRPX',
        'OBBO',
        'WVFZ',
        'WVMM',
        'CUNG'
    ];


    /**
     * Constructor.
     *
     * @param array $config Available options are `parts` and `partLength`.
     */
    public function __construct(array $config = [])
    {
        $config += [
            'parts'      => null,
            'partLength' => null
        ];
        if (isset($config['parts'])) {
            $this->_parts = $config['parts'];
        }
        if (isset($config['partLength'])) {
            $this->_partLength = $config['partLength'];
        }
    }


    /**
     * Generates a coupon code using the format `XXXX-XXXX-XXXX`.
     *
     * The 4th character of each part is a checkdigit.
     *
     * Not all letters and numbers are used, so if a person enters the letter 'O' we
     * can automatically correct it to the digit '0' (similarly for I => 1, S => 5, Z
     * => 2).
     *
     * The code generation algorithm avoids 'undesirable' codes. For example any code
     * in which transposed characters happen to result in a valid checkdigit will be
     * skipped.  Any generated part which happens to spell an 'inappropriate' 4-letter
     * word (e.g.: 'P00P') will also be skipped.
     *
     * @param string $random Allows to directly support a plaintext i.e. for testing.
     *
     * @return string Dash separated and normalized code.
     * @throws Exception
     */
    public function generate($random = null)
    {
        $results = [];

        $plaintext = $this->_convert($random ?: $this->_random(8));
        // String is already normalized by used alphabet.

        $part = $try = 0;
        while (count($results) < $this->_parts) {
            $result = substr($plaintext, $try * $this->_partLength, $this->_partLength - 1);

            if (! $result || strlen($result) !== $this->_partLength - 1) {
                throw new Exception('Ran out of plaintext.');
            }
            $result .= $this->_checkdigitAlg1($part + 1, $result);

            $try++;
            if ($this->_isBadWord($result) || $this->_isValidWhenSwapped($result)) {
                continue;
            }
            $part++;

            $results[] = $result;
        }

        return implode('-', $results);
    }


    /**
     * Validates given code. Codes are not case sensitive and
     * certain letters i.e. `O` are converted to digit equivalents
     * i.e. `0`.
     *
     * @param $code string Potentially unnormalized code.
     *
     * @return boolean
     */
    public function validate($code)
    {
        $code = $this->_normalize($code, ['clean' => true, 'case' => true]);

        if (strlen($code) !== ($this->_parts * $this->_partLength)) {
            return false;
        }
        $parts = str_split($code, $this->_partLength);

        foreach ($parts as $number => $part) {
            $expected = substr($part, -1);
            $result = $this->_checkdigitAlg1($number + 1, $x = substr($part, 0, strlen($part) - 1));

            if ($result !== $expected) {
                return false;
            }
        }

        return true;
    }


    /**
     * Implements the checkdigit algorithm #1 as used by the original library.
     *
     * @param integer $partNumber Number of the part.
     * @param string  $value      Actual part without the checkdigit.
     *
     * @return string The checkdigit symbol.
     */
    protected function _checkdigitAlg1($partNumber, $value)
    {
        $symbolsFlipped = array_flip($this->_symbols);
        $result = $partNumber;

        foreach (str_split($value) as $char) {
            $result = $result * 19 + $symbolsFlipped[$char];
        }

        return $this->_symbols[$result % (count($this->_symbols) - 1)];
    }


    /**
     * Verifies that a given value is a bad word.
     *
     * @param string $value
     *
     * @return boolean
     */
    protected function _isBadWord($value)
    {
        return isset($this->_badWords[str_rot13($value)]);
    }


    /**
     * Verifies that a given code part is still valid its symbols
     * are swapped (undesirable).
     *
     * @param string $value
     *
     * @return boolean
     */
    protected function _isValidWhenSwapped($value)
    {
        return false;
    }


    /**
     * Normalizes a given code using dash separators.
     *
     * @param string $string
     *
     * @return string
     */
    public function normalize($string)
    {
        $string = $this->_normalize($string, ['clean' => true, 'case' => true]);

        return implode('-', str_split($string, $this->_partLength));
    }


    /**
     * Converts givens string using symbols.
     *
     * @param string $string
     *
     * @return string
     */
    protected function _convert($string)
    {
        $symbols = $this->_symbols;

        $result = array_map(function ($value) use ($symbols) {
            return $symbols[ord($value) & (count($symbols) - 1)];
        }, str_split(hash('sha1', $string)));

        return implode('', $result);
    }


    /**
     * Internal method to normalize given strings.
     *
     * @param string $string
     * @param array  $options
     *
     * @return string
     */
    protected function _normalize($string, array $options = [])
    {
        $options += [
            'clean' => false,
            'case'  => false
        ];
        if ($options['case']) {
            $string = strtoupper($string);
        }
        $string = strtr($string, [
            'I' => 1,
            'O' => 0,
            'S' => 5,
            'Z' => 2,
        ]);

        if ($options['clean']) {
            $string = preg_replace('/[^0-9A-Z]+/', '', $string);
        }

        return $string;
    }


    /**
     * Generates a cryptographically secure sequence of bytes.
     *
     * @param integer $bytes Number of bytes to return.
     *
     * @return string
     * @throws Exception
     */
    protected function _random($bytes)
    {
        if (is_readable('/dev/urandom')) {
            $stream = fopen('/dev/urandom', 'rb');
            $result = fread($stream, $bytes);

            fclose($stream);

            return $result;
        }
        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($bytes, MCRYPT_DEV_RANDOM);
        }
        throw new Exception("No source for generating a cryptographically secure seed found.");
    }
}
