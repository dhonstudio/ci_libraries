<?php

Class DhonEmail {

    public function __construct()
	{
        $this->dhonemail =& get_instance();
    }

    public function message(string $title, string $img_logo, string $fullName, string $message, array $link, string $footer_message, string $author, string $api_wa, string $template = 'w3')
    {
        if ($template == 'w3') {
            $header = '
                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml">
                    <head>
                        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                        <title>'.$title.'</title>
                        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
                    </head>
                    <body style="margin: 0; padding: 20px 0 30px 0;">
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #cccccc;">
                            <tr>
                                <td align="center" bgcolor="#ffffff" style="padding: 40px 0 30px 0;">
                                    <a href="'. base_url() .'"><img src="'. $img_logo .'" alt="'.$author.'" width="200" style="display: block;" />
                                </td>
                            </tr>
                            <tr>
                                <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="color: #153643; font-family: Arial, sans-serif; font-size: 24px;">
                                                <b>Hello '.$fullName.',</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 20px 0 30px 0; color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 20px;">
                                                '.$message.'
                                            </td>
                                        </tr>';
            $link_message = '
                <tr>
                    <td>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td width="260" valign="top" style="padding: 20px 0 30px 0; color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 20px;">
                                    <a href="'.$link['href'].'" style="cursor: pointer;padding: .375rem .75rem;border-radius: .35rem;border: 1px solid transparent;transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;color: #fff;background-color: #858796;border-color: #858796;font-size: 1.5rem;line-height: 1.5;user-select: none;font-weight: 400;">'.$link['text'].'</a>
                                </td>
                                <td style="font-size: 0; line-height: 0;" width="20">
                                    &nbsp;
                                </td>
                                <td width="260" valign="top" style="padding: 20px 0 30px 0; color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 20px;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            ';
            $footer = '
                                        <tr>
                                            <td style="padding: 20px 0 0px 0; color: #153643; font-family: Arial, sans-serif; font-size: 12px;">
                                                '.$footer_message.'
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td bgcolor="#e4e4e7" style="padding: 30px 30px 30px 30px;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="color: #153643; font-family: Arial, sans-serif; font-size: 14px;">
                                                &trade; '.$author.' '.date('Y').'<br/>
                                                <a href="#" style="color: #153643;"><font color="#153643">Unsubscribe</font></a>
                                            </td>
                                            <td align="right">
                                                <table border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td>
                                                            <a href="'.$api_wa.'">
                                                            <img src="https://dhonstudio.com/assets/img/whatsapp-logo.png" alt="Whatsapp" width="38" height="38" style="display: block;" border="0" />
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </body>
                </html>
            ';
        }

        $final_message = $link ? $header.$link_message.$footer : $header.$footer;
        $this->email->message($final_message);
    }

}