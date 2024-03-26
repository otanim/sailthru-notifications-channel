<?php

namespace NotificationChannels\Sailthru;

use Exception;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sailthru_Client_Exception;

class SailthruChannel
{
    /**
     * @var SailthruClient
     */
    protected $sailthru;

    /**
     * @param SailthruClient $sailthru
     */
    public function __construct(
        SailthruClient $sailthru
    ) {
        $this->sailthru = $sailthru;
    }

    /**
     * Get default variables that are defined for all emails.
     *
     * Override this to use a different strategy.
     *
     * @return array
     */
    public static function getDefaultVars(): array
    {
        return [];
    }

    /**
     * @param $notifiable
     * @param Notification $notification
     *
     * @return array
     */
    public function send(
        $notifiable,
        Notification $notification
    ) {
        if (config('services.sailthru.enabled') === false) {
            Log::info(
                'Sending Sailthru message',
                [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                ]
            );

            return [];
        }

        try {
            /** @var SailthruMessage $message */
            $message = $notification->toSailthru($notifiable);
            $message->mergeDefaultVars(
                static::getDefaultVars()
            );

            if (config('services.sailthru.whitelist_check.enabled') === true) {
                if(!Str::is(
                    config('services.sailthru.whitelist_check.domains'),
                    $message->getToEmail()
                )){
                    Log::info(
                        'Sailthru email not sent to ' . $message->getToEmail() . ' due to domain whitelist limitations',
                        [
                            'notifiable' => $notifiable,
                            'notification' => $notification,
                        ]
                    );

                    return [];
                }
            }

            $response = $message->isMultiSend()
                ? $this->multiSend($message)
                : $this->singleSend($message);

            Event::dispatch(
                new NotificationSent(
                    $notifiable,
                    $notification,
                    'sailthru',
                    [
                        'message' => $message,
                        'response' => $response,
                    ]
                )
            );

            return $response;
        } catch (Exception $e) {
            Event::dispatch(
                new NotificationFailed(
                    $notifiable,
                    $notification,
                    'sailthru',
                    [
                        'message' => isset($message) ? $message : null,
                        'exception' => $e,
                    ]
                )
            );

            return [];
        }
    }

    /**
     * @param SailthruMessage $sailthruMessage
     *
     * @throws Sailthru_Client_Exception
     *
     * @return array
     */
    protected function multiSend(
        SailthruMessage $sailthruMessage
    ) {
        $template = $sailthruMessage->getTemplate();
        $toEmail = $sailthruMessage->getToEmail();
        $vars = $sailthruMessage->getVars();
        $eVars = $sailthruMessage->getEVars();
        $options = $sailthruMessage->getOptions();

        if (config('services.sailthru.log_payload') === true) {
            Log::debug(
                'Sailthru Payload',
                [
                    'template' => $template,
                    'email' => $toEmail,
                    'vars' => $vars,
                    'eVars' => $eVars,
                    'options' => $options,
                ]
            );
        }

        return $this->sailthru->multisend(
            $template,
            $toEmail,
            $vars,
            $eVars,
            $options
        );
    }

    /**
     * @param SailthruMessage $sailthruMessage
     *
     * @throws Sailthru_Client_Exception
     *
     * @return array
     */
    protected function singleSend(
        SailthruMessage $sailthruMessage
    ) {
        $template = $sailthruMessage->getTemplate();
        $toEmail = $sailthruMessage->getToEmail();
        $vars = $sailthruMessage->getVars();
        $options = $sailthruMessage->getOptions();

        if (config('services.sailthru.log_payload') === true) {
            Log::debug(
                'Sailthru Payload',
                [
                    'template' => $template,
                    'email' => $toEmail,
                    'vars' => $vars,
                    'options' => $options,
                ]
            );
        }

        return $this->sailthru->send(
            $template,
            $toEmail,
            $vars,
            $options
        );
    }
}
