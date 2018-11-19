<?php
/**
 * @author    eComCharge Team
 * @copyright Copyright (c) 2018 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayLog
{
    /**
     * Save exception to log file.
     * @param Exception $e
     */
    public static function saveException(\Exception $e)
    {
        $data = [
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ];

        self::save($data);
    }

    /**
     * Save standard log message to log file.
     * @param $message
     * @param array $additionalData
     */
    public static function saveLog($message, array $additionalData = [])
    {
        if (empty($message)) {
            return;
        }

        $data['message']    = $message;
        $data['additional'] = $additionalData;

        self::save($data);
    }

    /**
     * Method sending required data to log file.
     * @param array $data
     */
    public static function save(array $data)
    {
        $data['time'] = date('Y-m-d H:i:s');

        $logger = new FileLogger();
        $logger->setFilename(_PS_ROOT_DIR_ . '/log/' . date('Ymd') . '_begateway.log');
        $logger->logError(Tools::jsonEncode($data));
    }
}
