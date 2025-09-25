<?php
// classes/EmailHandler.php - Email Notification System
class EmailHandler {
    
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $isEnabled;
    
    public function __construct() {
        $this->smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $this->smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $this->smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $this->fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@jhubafrica.com';
        $this->fromName = defined('FROM_NAME') ? FROM_NAME : 'JHUB AFRICA';
        $this->isEnabled = defined('SMTP_ENABLED') ? SMTP_ENABLED : false;
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $type = 'general', $additionalData = []) {
        try {
            // Store in database first
            $notificationId = $this->storeNotification($to, $subject, $message, $type, $additionalData);
            
            if (!$this->isEnabled) {
                // Email sending is disabled, just log it
                $this->logEmail($to, $subject, 'Email sending disabled - stored only', 'info');
                
                // Update notification status
                $this->updateNotificationStatus($notificationId, 'pending', 'SMTP disabled');
                
                return [
                    'success' => true,
                    'message' => 'Email queued (SMTP disabled)',
                    'notification_id' => $notificationId
                ];
            }
            
            // Try to send email
            $result = $this->sendViaPhpMailer($to, $subject, $message);
            
            if ($result['success']) {
                $this->updateNotificationStatus($notificationId, 'sent');
                $this->logEmail($to, $subject, 'Email sent successfully', 'success');
            } else {
                $this->updateNotificationStatus($notificationId, 'failed', $result['error']);
                $this->logEmail($to, $subject, 'Email failed: ' . $result['error'], 'error');
            }
            
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Email sent successfully' : 'Email sending failed: ' . $result['error'],
                'notification_id' => $notificationId
            ];
            
        } catch (Exception $e) {
            $this->logEmail($to, $subject, 'Exception: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Email system error: ' . $e->getMessage(),
                'notification_id' => null
            ];
        }
    }
    
    /**
     * Send email using PHP's mail function (fallback)
     */
    private function sendViaPhpMailer($to, $subject, $message) {
        try {
            // Prepare headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
                'Reply-To: ' . $this->fromEmail,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            // Convert plain text to basic HTML if needed
            if (strip_tags($message) === $message) {
                $message = nl2br(htmlspecialchars($message));
            }
            
            // Add basic HTML structure
            $htmlMessage = $this->wrapInHtmlTemplate($message, $subject);
            
            // Send email
            $success = mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
            
            return [
                'success' => $success,
                'error' => $success ? null : 'PHP mail() function failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Wrap message in basic HTML email template
     */
    private function wrapInHtmlTemplate($message, $subject) {
        $baseUrl = getBaseUrl();
        $currentYear = date('Y');
        
        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .footer { background: #343a40; color: #adb5bd; padding: 20px; text-align: center; font-size: 12px; }
        .logo { font-size: 24px; font-weight: bold; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <div class='logo'>🚀 JHUB AFRICA</div>
            <p>Nurturing African Innovation</p>
        </div>
        
        <div class='content'>
            {$message}
        </div>
        
        <div class='footer'>
            <p>&copy; {$currentYear} JHUB AFRICA. All rights reserved.</p>
            <p>
                <a href='{$baseUrl}' style='color: #adb5bd;'>Visit Website</a> | 
                <a href='{$baseUrl}/contact' style='color: #adb5bd;'>Contact Us</a>
            </p>
            <small>This email was sent from an automated system. Please do not reply directly to this email.</small>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Store notification in database
     */
    private function storeNotification($to, $subject, $message, $type, $additionalData) {
        $database = Database::getInstance();
        
        $data = [
            'recipient_email' => $to,
            'recipient_name' => $additionalData['recipient_name'] ?? null,
            'subject' => $subject,
            'message_body' => $message,
            'notification_type' => $type,
            'related_project_id' => $additionalData['project_id'] ?? null,
            'related_application_id' => $additionalData['application_id'] ?? null,
            'status' => 'pending'
        ];
        
        return $database->insert('email_notifications', $data);
    }
    
    /**
     * Update notification status
     */
    private function updateNotificationStatus($notificationId, $status, $errorMessage = null) {
        if (!$notificationId) return false;
        
        $database = Database::getInstance();
        
        $data = [
            'status' => $status,
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'error_message' => $errorMessage,
            'attempts' => new PDOStatement('attempts + 1') // This would need proper handling
        ];
        
        // For now, simple update
        $database->update(
            'email_notifications',
            [
                'status' => $status,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                'error_message' => $errorMessage
            ],
            'notification_id = ?',
            [$notificationId]
        );
    }
    
    /**
     * Log email activity
     */
    private function logEmail($to, $subject, $message, $level = 'info') {
        $logMessage = date('Y-m-d H:i:s') . " [{$level}] Email to {$to}: {$subject} - {$message}\n";
        error_log($logMessage, 3, dirname(__DIR__) . '/logs/email.log');
        
        // Also log to activity logs if function exists
        if (function_exists('logActivity')) {
            logActivity('system', null, 'email_' . $level, "Email to {$to}: {$subject} - {$message}");
        }
    }
    
    /**
     * Get pending email notifications
     */
    public function getPendingNotifications($limit = 50) {
        $database = Database::getInstance();
        
        return $database->getRows("
            SELECT * FROM email_notifications 
            WHERE status = 'pending' AND attempts < 3
            ORDER BY created_at ASC 
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications() {
        $pendingNotifications = $this->getPendingNotifications();
        $results = [];
        
        foreach ($pendingNotifications as $notification) {
            $result = $this->sendEmail(
                $notification['recipient_email'],
                $notification['subject'],
                $notification['message_body'],
                $notification['notification_type'],
                [
                    'project_id' => $notification['related_project_id'],
                    'application_id' => $notification['related_application_id']
                ]
            );
            
            $results[] = [
                'notification_id' => $notification['notification_id'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
        
        return $results;
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        $database = Database::getInstance();
        
        return [
            'total' => $database->count('email_notifications'),
            'sent' => $database->count('email_notifications', 'status = ?', ['sent']),
            'pending' => $database->count('email_notifications', 'status = ?', ['pending']),
            'failed' => $database->count('email_notifications', 'status = ?', ['failed']),
            'today' => $database->count('email_notifications', 'DATE(created_at) = ?', [date('Y-m-d')])
        ];
    }
    
    /**
     * Clean old notifications
     */
    public function cleanOldNotifications($daysOld = 30) {
        $database = Database::getInstance();
        
        $result = $database->delete(
            'email_notifications',
            'created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND status = ?',
            [$daysOld, 'sent']
        );
        
        return $result ? $database->getConnection()->rowCount() : 0;
    }
}

// Global function for backward compatibility
if (!function_exists('sendEmailNotification')) {
    function sendEmailNotification($to, $subject, $message, $type = 'general', $additionalData = []) {
        $emailHandler = new EmailHandler();
        return $emailHandler->sendEmail($to, $subject, $message, $type, $additionalData);
    }
}
?>