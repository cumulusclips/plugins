<?php

class GoogleAnalytics
{
    /**
     * Attaches plugin to plugin's HTML Head
     */
    public static function Load()
    {
        Plugin::Attach('theme.head', array(__CLASS__, 'AddTrackingCode'));
    }

    /**
     * Provides information regarding plugin to Admin Panel
     * @return array Plugin information
     */
    public static function Info()
    {
        return array(
            'name'    => 'Google Analytics',
            'author'  => 'CumulusClips',
            'version' => '1.0.0',
            'site'    => 'http://cumulusclips.org/',
            'notes'   => 'Attaches Google Analytics tracking code to your website.'
        );
    }

    /**
     * Outputs Google Analytics Javascript into DOM
     */
    public static function AddTrackingCode()
    {
        $code = Settings::Get('GoogleAnalytics.code');
        include(dirname(__FILE__) . '/TrackingCode.phtml');
    }

    /**
     * Display and process the settings form for the plugin
     */
    public static function Settings()
    {
        $success = false;
        $errors = false;

        // Validate form if submitted
        if (isset($_POST['submitted'])) {
            if (!empty($_POST['code'])) {
                Settings::Set('GoogleAnalytics.code', $_POST['code']);
                $success = true;
            } else {
                $errors = true;
            }
        }

        // Render form
        $code = Settings::Get('GoogleAnalytics.code');
        include(dirname(__FILE__) . '/Settings.phtml');
    }

    /**
     * Remove settings stored in db by plugin
     */
    public static function Uninstall()
    {
        Settings::Remove('GoogleAnalytics.code');
    }
}