<?php

namespace App\Support;

/**
 * Builds the HTML for email signatures and email templates.
 *
 * Email programs (Gmail, Outlook, Apple Mail, phone apps) ignore most modern CSS, so everything
 * here is laid out with tables and inline styles, with no scripts, no web fonts and no SVG.
 * Social links are drawn as coloured table cells with letters, so they need no image files.
 * Every value is escaped; the only HTML let through is template body text cleaned by RichText.
 */
class EmailBuilder
{
    public const SIGNATURE_LAYOUTS = [
        'classic' => 'Classic: photo on the left, details beside it',
        'modern' => 'Modern: coloured top line, name in colour, logo on the right',
        'compact' => 'Compact: no photo, a few short lines',
        'showcase' => 'Showcase: large photo, logo on the right, icons, a highlight line and a banner below',
    ];

    public const TEMPLATE_LAYOUTS = [
        'clean' => 'Clean: logo on white, coloured line under it',
        'band' => 'Band: solid colour header with the logo or name',
        'hero' => 'Hero: solid colour header, made to sit above a banner image',
    ];

    public const FONTS = [
        'arial' => ['Arial', 'Arial, Helvetica, sans-serif'],
        'verdana' => ['Verdana', 'Verdana, Geneva, sans-serif'],
        'tahoma' => ['Tahoma', 'Tahoma, Geneva, sans-serif'],
        'trebuchet' => ['Trebuchet MS', "'Trebuchet MS', Helvetica, sans-serif"],
        'georgia' => ['Georgia', "Georgia, 'Times New Roman', serif"],
    ];

    public const BANNER_POSITIONS = ['top' => 'Under the header', 'middle' => 'After the message', 'bottom' => 'Just above the footer'];

    public const PHOTO_SHAPES = ['circle' => 'Circle', 'rounded' => 'Rounded square', 'square' => 'Square'];

    public const SOCIAL_STYLES = ['badges' => 'Round badges', 'squares' => 'Square badges', 'text' => 'Text links', 'none' => 'Hide'];

    /** network => [name, letters on the badge, brand colour] */
    public const SOCIALS = [
        'linkedin' => ['LinkedIn', 'in', '#0a66c2'],
        'facebook' => ['Facebook', 'f', '#1877f2'],
        'instagram' => ['Instagram', 'IG', '#c13584'],
        'x' => ['X', 'X', '#111111'],
        'youtube' => ['YouTube', 'YT', '#d92323'],
        'whatsapp' => ['WhatsApp', 'WA', '#128c4b'],
    ];

    // ------------------------------------------------------------------ helpers

    public static function e(?string $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escaped text with its line breaks kept as <br>. */
    public static function lines(?string $value): string
    {
        return preg_replace('/\r\n|\r|\n/', '<br>', self::e($value));
    }

    /** A web address safe to put in a link or image: http(s) only. Adds https:// to "www.example.com". */
    public static function url(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (! preg_match('~^https?://~i', $value)) {
            // Anything with another scheme (javascript:, data:, ftp:, mailto:…) is refused, not "fixed".
            if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $value)) {
                return '';
            }
            $value = 'https://'.ltrim($value, '/');
        }

        return filter_var($value, FILTER_VALIDATE_URL) && preg_match('~^https?://[^\s"\'<>]+$~i', $value) ? $value : '';
    }

    public static function color(?string $value, string $fallback): string
    {
        return Branding::validHex($value) ?: $fallback;
    }

    public static function font(?string $key): string
    {
        return self::FONTS[$key][1] ?? self::FONTS['arial'][1];
    }

    /** White or near-black, whichever reads better on the given colour. */
    public static function on(string $color): string
    {
        return Branding::contrastWithWhite($color) >= 3 ? '#ffffff' : '#1c1917';
    }

    /** @param array<string, ?string> $links network => address */
    protected static function socials(array $links, string $style, string $font, string $accent, bool $brandColors, string $align = 'left'): string
    {
        $links = collect(self::SOCIALS)->map(fn ($meta, $network) => self::url($links[$network] ?? ''))->filter();
        if ($style === 'none' || $links->isEmpty()) {
            return '';
        }

        if ($style === 'text') {
            return $links->map(fn ($url, $network) => '<a href="'.self::e($url).'" target="_blank" style="color:'.$accent.';text-decoration:none;font-family:'.$font.';font-size:12px;">'.self::SOCIALS[$network][0].'</a>')
                ->implode('<span style="color:#b7b0aa;font-size:12px;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>');
        }

        // Round badges are 26px circles; square ones are a little larger with barely rounded corners.
        [$size, $radius, $letterSize] = $style === 'squares' ? [30, 3, 12] : [26, 13, 10];
        $cells = $links->map(function ($url, $network) use ($font, $accent, $brandColors, $size, $radius, $letterSize) {
            [$name, $letters, $brand] = self::SOCIALS[$network];
            $bg = $brandColors ? $brand : $accent;

            return '<td style="padding:0 6px 0 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                .'<td align="center" valign="middle" width="'.$size.'" height="'.$size.'" bgcolor="'.$bg.'" style="width:'.$size.'px;height:'.$size.'px;border-radius:'.$radius.'px;background-color:'.$bg.';text-align:center;">'
                .'<a href="'.self::e($url).'" target="_blank" title="'.$name.'" style="display:block;width:'.$size.'px;line-height:'.$size.'px;color:'.self::on($bg).';text-decoration:none;font-family:'.$font.';font-size:'.$letterSize.'px;font-weight:bold;">'.$letters.'</a>'
                .'</td></tr></table></td>';
        })->implode('');

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'.($align === 'center' ? ' align="center"' : '').'><tr>'.$cells.'</tr></table>';
    }

    // --------------------------------------------------------------- signature

    /** Defaults for a new signature. */
    public static function signatureDefaults(): array
    {
        return [
            'layout' => 'classic', 'name' => '', 'title' => '', 'company' => '', 'phone' => '', 'mobile' => '', 'email' => '', 'website' => '', 'address' => '',
            'photo_url' => '', 'photo_shape' => 'circle', 'logo_url' => '', 'accent' => '', 'text_color' => '#1c1917', 'font' => 'arial',
            'social_style' => 'badges', 'social_brand_colors' => '1',
            'linkedin' => '', 'facebook' => '', 'instagram' => '', 'x' => '', 'youtube' => '', 'whatsapp' => '', 'note' => '',
            // Showcase layout: colour behind a cut-out photo, a highlighted line with a link, and a wide banner image.
            'photo_bg' => '', 'highlight_label' => '', 'highlight_text' => '', 'highlight_url' => '', 'banner_url' => '', 'banner_link' => '',
        ];
    }

    /**
     * The Showcase layout: a large photo on the left; name, coloured designation and the logo
     * across the top; a rule; contact lines with small symbols; a starred line with a link;
     * social badges; and a wide banner image underneath.
     */
    protected static function showcase(array $d, string $font, string $accent, string $text): string
    {
        $grey = '#5f6368';
        $photo = self::url($d['photo_url']);
        $logo = self::url($d['logo_url']);
        $site = self::url($d['website']);
        $email = filter_var(trim($d['email']), FILTER_VALIDATE_EMAIL) ?: '';
        $radius = ['circle' => '50%', 'rounded' => '14px', 'square' => '0'][$d['photo_shape']] ?? '50%';
        $photoBg = Branding::validHex($d['photo_bg']);
        $tel = fn (string $number) => 'tel:'.preg_replace('/[^0-9+]/', '', $number);

        // One contact line: a symbol in its own narrow cell so the text lines up, then the value.
        $row = fn (string $symbol, string $html, string $symbolColor = '#6b7280') => '<tr>'
            .'<td width="26" valign="middle" style="width:26px;padding:3px 0;font-family:'.$font.';font-size:14px;line-height:20px;color:'.$symbolColor.';">'.$symbol.'</td>'
            .'<td valign="middle" style="padding:3px 0;font-family:'.$font.';font-size:14.5px;line-height:20px;color:'.$grey.';">'.$html.'</td></tr>';
        $plain = fn (string $href, string $label) => '<a href="'.self::e($href).'" style="color:'.$grey.';text-decoration:none;">'.$label.'</a>';

        $rows = '';
        if ($email) {
            $rows .= $row('&#9993;', $plain('mailto:'.$email, self::e($email)));
        }
        if (trim($d['phone']) !== '') {
            $rows .= $row('&#9742;', $plain($tel($d['phone']), self::e($d['phone'])));
        }
        if (trim($d['mobile']) !== '') {
            $rows .= $row('&#9990;', $plain($tel($d['mobile']), self::e($d['mobile'])));
        }
        if ($site !== '') {
            $rows .= $row('&#127760;', '<a href="'.self::e($site).'" target="_blank" style="color:'.$grey.';text-decoration:none;">'.self::e(preg_replace('~^https?://|/$~i', '', $site)).'</a>');
        }
        if (trim($d['address']) !== '') {
            $rows .= $row('&#9873;', self::e(preg_replace('/\s*[\r\n]+\s*/', ', ', $d['address'])));
        }
        $highlightUrl = self::url($d['highlight_url']);
        if (trim($d['highlight_label']) !== '' || trim($d['highlight_text']) !== '') {
            $label = trim($d['highlight_label']) !== '' ? '<strong style="color:'.$grey.';">'.self::e($d['highlight_label']).'</strong>' : '';
            $linkText = self::e($d['highlight_text']);
            $link = $linkText === '' ? '' : ($highlightUrl !== ''
                ? '<a href="'.self::e($highlightUrl).'" target="_blank" style="color:'.$grey.';text-decoration:underline;">'.$linkText.'</a>'
                : $linkText);
            $rows .= $row('&#9733;', $label.($label !== '' && $link !== '' ? ' <strong style="color:'.$grey.';">|</strong>&nbsp; ' : '').$link, '#f5b400');
        }

        $socials = self::socials($d, $d['social_style'], $font, $accent, $d['social_brand_colors'] === '1');
        $banner = self::url($d['banner_url']);
        $bannerLink = self::url($d['banner_link']);
        $bannerTag = $banner !== '' ? '<img src="'.self::e($banner).'" width="600" alt="'.self::e($d['company']).'" style="display:block;width:100%;max-width:600px;height:auto;border:0;border-radius:10px;">' : '';
        if ($bannerTag !== '' && $bannerLink !== '') {
            $bannerTag = '<a href="'.self::e($bannerLink).'" target="_blank" style="text-decoration:none;">'.$bannerTag.'</a>';
        }

        return '<tr>'
            .($photo !== '' ? '<td valign="top" width="126" style="width:126px;padding:6px 24px 0 0;">'
                .'<img src="'.self::e($photo).'" width="126" height="126" alt="'.self::e($d['name']).'" style="display:block;width:126px;height:126px;border-radius:'.$radius.';border:0;'.($photoBg ? 'background-color:'.$photoBg.';' : '').'"></td>' : '')
            .'<td valign="top" style="padding:0;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            .'<td valign="top" style="padding:0 0 10px;">'
            .'<span style="font-family:'.$font.';font-size:19px;line-height:26px;font-weight:bold;color:'.$text.';">'.self::e($d['name']).'</span>'
            .(trim($d['title']) !== '' ? '<br><span style="font-family:'.$font.';font-size:16.5px;line-height:25px;color:'.$accent.';">'.self::e($d['title']).'</span>' : '')
            .(trim($d['company']) !== '' && $logo === '' ? '<br><span style="font-family:'.$font.';font-size:13px;line-height:20px;color:'.$grey.';">'.self::e($d['company']).'</span>' : '')
            .'</td>'
            .($logo !== '' ? '<td valign="top" align="right" style="padding:0 0 10px 16px;"><img src="'.self::e($logo).'" height="36" alt="'.self::e($d['company']).'" style="display:block;height:36px;width:auto;max-width:150px;border:0;"></td>' : '')
            .'</tr><tr><td colspan="2" style="border-top:1px solid #d5d5d5;padding:0 0 8px;font-size:0;line-height:0;">&nbsp;</td></tr></table>'
            .($rows !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$rows.'</table>' : '')
            .($socials !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:16px 0 0;">'.$socials.'</td></tr></table>' : '')
            .'</td></tr>'
            .($bannerTag !== '' ? '<tr><td colspan="2" style="padding:18px 0 0;">'.$bannerTag.'</td></tr>' : '');
    }

    public static function signature(array $data): string
    {
        $d = array_merge(self::signatureDefaults(), array_map(fn ($v) => is_string($v) ? $v : (string) $v, $data));
        $font = self::font($d['font']);
        $accent = self::color($d['accent'], Branding::shade(600));
        $text = self::color($d['text_color'], '#1c1917');
        $muted = '#6b635d';
        $photo = self::url($d['photo_url']);
        $logo = self::url($d['logo_url']);
        $site = self::url($d['website']);
        $siteLabel = self::e(preg_replace('~^https?://(www\.)?|/$~i', '', $site));
        $email = filter_var(trim($d['email']), FILTER_VALIDATE_EMAIL) ?: '';
        $tel = fn (string $number) => 'tel:'.preg_replace('/[^0-9+]/', '', $number);
        $radius = ['circle' => '50%', 'rounded' => '10px', 'square' => '0'][$d['photo_shape']] ?? '50%';
        $base = "font-family:{$font};color:{$text};";

        $name = '<span style="'.$base.'font-size:17px;font-weight:bold;line-height:22px;">'.self::e($d['name']).'</span>';
        $role = trim(self::e($d['title']).($d['title'] !== '' && $d['company'] !== '' ? ' &nbsp;|&nbsp; ' : '').self::e($d['company']));
        $role = $role !== '' ? '<span style="font-family:'.$font.';color:'.$muted.';font-size:13px;line-height:19px;">'.$role.'</span>' : '';

        // Contact lines, each with a small coloured label.
        $line = fn (string $label, string $html) => '<tr><td style="font-family:'.$font.';font-size:12.5px;line-height:19px;color:'.$text.';padding:0;">'
            .'<span style="color:'.$accent.';font-weight:bold;">'.$label.'</span>&nbsp; '.$html.'</td></tr>';
        $link = fn (string $href, string $label) => '<a href="'.self::e($href).'" style="color:'.$text.';text-decoration:none;">'.$label.'</a>';
        $contact = '';
        if (trim($d['phone']) !== '') {
            $contact .= $line('T', $link($tel($d['phone']), self::e($d['phone'])));
        }
        if (trim($d['mobile']) !== '') {
            $contact .= $line('M', $link($tel($d['mobile']), self::e($d['mobile'])));
        }
        if ($email) {
            $contact .= $line('E', $link('mailto:'.$email, self::e($email)));
        }
        if ($site !== '') {
            $contact .= $line('W', '<a href="'.self::e($site).'" target="_blank" style="color:'.$accent.';text-decoration:none;">'.$siteLabel.'</a>');
        }
        if (trim($d['address']) !== '') {
            $contact .= $line('A', '<span style="color:'.$muted.';">'.self::e(preg_replace('/\s*[\r\n]+\s*/', ', ', $d['address'])).'</span>');
        }
        $contact = $contact !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$contact.'</table>' : '';

        $socials = self::socials($d, $d['social_style'], $font, $accent, $d['social_brand_colors'] === '1');
        $photoTag = $photo !== '' ? '<img src="'.self::e($photo).'" width="88" height="88" alt="'.self::e($d['name']).'" style="display:block;width:88px;height:88px;border-radius:'.$radius.';border:0;">' : '';
        $logoTag = $logo !== '' ? '<img src="'.self::e($logo).'" height="34" alt="'.self::e($d['company']).'" style="display:block;height:34px;width:auto;max-width:150px;border:0;">' : '';
        $note = trim($d['note']) !== '' ? '<tr><td style="padding:10px 0 0;font-family:'.$font.';font-size:10.5px;line-height:15px;color:#8a827b;">'.self::lines($d['note']).'</td></tr>' : '';
        $gap = fn (int $px) => '<tr><td height="'.$px.'" style="height:'.$px.'px;line-height:'.$px.'px;font-size:0;">&nbsp;</td></tr>';

        $inner = match ($d['layout']) {
            'showcase' => self::showcase($d, $font, $accent, $text),

            'modern' => '<tr><td style="border-top:3px solid '.$accent.';padding:12px 0 0;">'
                .'<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                .($photoTag !== '' ? '<td valign="top" style="padding:0 14px 0 0;">'.$photoTag.'</td>' : '')
                .'<td valign="top" style="padding:0;">'
                .'<span style="font-family:'.$font.';color:'.$accent.';font-size:18px;font-weight:bold;line-height:23px;">'.self::e($d['name']).'</span>'
                .($role !== '' ? '<br>'.$role : '')
                .'<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$gap(8).'</table>'.$contact
                .($socials !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$gap(9).'<tr><td>'.$socials.'</td></tr></table>' : '')
                .'</td>'
                .($logoTag !== '' ? '<td valign="top" style="padding:0 0 0 22px;">'.$logoTag.'</td>' : '')
                .'</tr></table></td></tr>',

            'compact' => '<tr><td style="padding:0;">'
                .$name.($role !== '' ? '<span style="font-family:'.$font.';color:#b7b0aa;font-size:13px;">&nbsp;&nbsp;·&nbsp;&nbsp;</span>'.$role : '')
                .'</td></tr>'
                .'<tr><td style="padding:5px 0 0;font-family:'.$font.';font-size:12.5px;line-height:19px;color:'.$text.';border-top:0;">'
                .collect([
                    trim($d['phone']) !== '' ? $link($tel($d['phone']), self::e($d['phone'])) : null,
                    trim($d['mobile']) !== '' ? $link($tel($d['mobile']), self::e($d['mobile'])) : null,
                    $email ? $link('mailto:'.$email, self::e($email)) : null,
                    $site !== '' ? '<a href="'.self::e($site).'" target="_blank" style="color:'.$accent.';text-decoration:none;">'.$siteLabel.'</a>' : null,
                ])->filter()->implode('<span style="color:'.$accent.';">&nbsp;&nbsp;|&nbsp;&nbsp;</span>')
                .'</td></tr>'
                .($socials !== '' ? $gap(8).'<tr><td>'.$socials.'</td></tr>' : '')
                .($logoTag !== '' ? $gap(10).'<tr><td>'.$logoTag.'</td></tr>' : ''),

            default => '<tr><td style="padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                .($photoTag !== '' || $logoTag !== '' ? '<td valign="top" align="center" style="padding:0 16px 0 0;">'.$photoTag.($photoTag !== '' && $logoTag !== '' ? '<div style="height:10px;line-height:10px;font-size:0;">&nbsp;</div>' : '').$logoTag.'</td>' : '')
                .'<td valign="top" style="padding:0 0 0 16px;border-left:2px solid '.$accent.';">'
                .$name.($role !== '' ? '<br>'.$role : '')
                .'<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$gap(8).'</table>'.$contact
                .($socials !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0">'.$gap(9).'<tr><td>'.$socials.'</td></tr></table>' : '')
                .'</td></tr></table></td></tr>',
        };

        // The Showcase layout is as wide as an email (600px) so its banner fills the line; the others stay compact.
        $wide = $d['layout'] === 'showcase';
        if ($wide) {
            $note = str_replace('<tr><td style="padding:10px 0 0;', '<tr><td colspan="2" style="padding:12px 0 0;', $note);
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'.($wide ? ' width="600"' : '').' style="border-collapse:collapse;'.($wide ? 'width:100%;max-width:600px;' : 'max-width:520px;').$base.'">'.$inner.$note.'</table>';
    }

    // ---------------------------------------------------------------- template

    public static function templateDefaults(): array
    {
        return [
            'layout' => 'band', 'company' => '', 'logo_url' => '', 'accent' => '', 'background' => '#f3f2ef', 'font' => 'arial',
            'preheader' => '', 'hero_url' => '', 'heading' => 'Hello,', 'body' => '', 'button_label' => '', 'button_url' => '',
            'hero_link' => '', 'banner_position' => 'top', 'banner_alt' => '',
            'signoff' => '', 'footer_text' => '', 'footer_address' => '', 'footer_phone' => '', 'footer_email' => '', 'website' => '', 'footer_note' => '',
            'social_style' => 'badges', 'social_brand_colors' => '0',
            'linkedin' => '', 'facebook' => '', 'instagram' => '', 'x' => '', 'youtube' => '', 'whatsapp' => '',
        ];
    }

    /** Body text from the editor, cleaned and given the inline styles email programs need. */
    protected static function emailBody(?string $html, string $font, string $accent): string
    {
        $html = RichText::render($html);
        $p = 'margin:0 0 14px;font-family:'.$font.';font-size:15px;line-height:24px;color:#33302d;';

        return strtr($html, [
            '<div>' => '<div style="'.$p.'">', '<p>' => '<p style="'.$p.'">',
            '<ul>' => '<ul style="margin:0 0 14px;padding:0 0 0 22px;font-family:'.$font.';font-size:15px;line-height:24px;color:#33302d;">',
            '<ol>' => '<ol style="margin:0 0 14px;padding:0 0 0 22px;font-family:'.$font.';font-size:15px;line-height:24px;color:#33302d;">',
            '<li>' => '<li style="margin:0 0 4px;">',
            '<h1>' => '<h2 style="margin:6px 0 8px;font-family:'.$font.';font-size:18px;line-height:24px;color:#1c1917;">', '</h1>' => '</h2>',
            '<h2>' => '<h2 style="margin:6px 0 8px;font-family:'.$font.';font-size:18px;line-height:24px;color:#1c1917;">',
            '<h3>' => '<h3 style="margin:6px 0 8px;font-family:'.$font.';font-size:16px;line-height:22px;color:#1c1917;">',
            '<blockquote>' => '<blockquote style="margin:0 0 14px;padding:2px 0 2px 14px;border-left:3px solid '.$accent.';font-family:'.$font.';font-size:15px;line-height:24px;color:#57534e;">',
            '<a href=' => '<a style="color:'.$accent.';text-decoration:underline;" href=',
        ]);
    }

    /** A complete HTML email: 600px wide on a computer, full width on a phone. */
    public static function template(array $data): string
    {
        $d = array_merge(self::templateDefaults(), array_map(fn ($v) => is_string($v) ? $v : (string) $v, $data));
        $font = self::font($d['font']);
        $accent = self::color($d['accent'], Branding::shade(600));
        $on = self::on($accent);
        $bg = self::color($d['background'], '#f3f2ef');
        $logo = self::url($d['logo_url']);
        $hero = self::url($d['hero_url']);
        $site = self::url($d['website']);
        $button = self::url($d['button_url']);
        $company = self::e($d['company']);
        $band = in_array($d['layout'], ['band', 'hero'], true);

        $brand = $logo !== ''
            ? '<img src="'.self::e($logo).'" height="40" alt="'.$company.'" style="display:block;height:40px;width:auto;max-width:220px;border:0;'.($band ? '' : 'margin:0;').'">'
            : '<span style="font-family:'.$font.';font-size:22px;font-weight:bold;line-height:28px;color:'.($band ? $on : '#1c1917').';">'.$company.'</span>';
        if ($site !== '') {
            $brand = '<a href="'.self::e($site).'" target="_blank" style="text-decoration:none;">'.$brand.'</a>';
        }

        $header = $band
            ? '<tr><td class="px" bgcolor="'.$accent.'" style="background-color:'.$accent.';padding:24px 36px;">'.$brand.'</td></tr>'
            : '<tr><td class="px" style="padding:28px 36px 20px;border-bottom:3px solid '.$accent.';">'.$brand.'</td></tr>';

        // The banner is a plain image, full width, optionally a link. It can sit under the header, after the message or above the footer.
        $bannerImg = $hero !== '' ? '<img src="'.self::e($hero).'" width="600" alt="'.self::e($d['banner_alt']).'" style="display:block;width:100%;max-width:600px;height:auto;border:0;">' : '';
        if ($bannerImg !== '' && ($heroLink = self::url($d['hero_link'])) !== '') {
            $bannerImg = '<a href="'.self::e($heroLink).'" target="_blank" style="text-decoration:none;">'.$bannerImg.'</a>';
        }
        $position = array_key_exists($d['banner_position'], self::BANNER_POSITIONS) ? $d['banner_position'] : 'top';
        $bannerRow = fn (string $at) => $bannerImg !== '' && $position === $at ? '<tr><td style="padding:0;">'.$bannerImg.'</td></tr>'."\n" : '';
        $heroRow = $bannerRow('top');

        $buttonRow = $button !== '' && trim($d['button_label']) !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 18px;"><tr><td align="center" bgcolor="'.$accent.'" style="background-color:'.$accent.';border-radius:6px;">'
              .'<a href="'.self::e($button).'" target="_blank" style="display:inline-block;padding:13px 28px;font-family:'.$font.';font-size:15px;font-weight:bold;line-height:20px;color:'.$on.';text-decoration:none;">'.self::e($d['button_label']).'</a></td></tr></table>' : '';

        $signoff = trim($d['signoff']) !== '' ? '<p style="margin:6px 0 0;font-family:'.$font.';font-size:15px;line-height:24px;color:#33302d;">'.self::lines($d['signoff']).'</p>' : '';
        $socials = self::socials($d, $d['social_style'], $font, $accent, $d['social_brand_colors'] === '1', 'center');
        $footerText = trim($d['footer_text']) !== '' ? self::lines($d['footer_text']) : $company;
        // Address on its own lines, then phone and email side by side.
        $footerEmail = filter_var(trim($d['footer_email']), FILTER_VALIDATE_EMAIL) ?: '';
        $contactBits = array_filter([
            trim($d['footer_phone']) !== '' ? '<a href="tel:'.preg_replace('/[^0-9+]/', '', $d['footer_phone']).'" style="color:#6b635d;text-decoration:none;">'.self::e($d['footer_phone']).'</a>' : '',
            $footerEmail ? '<a href="mailto:'.self::e($footerEmail).'" style="color:#6b635d;text-decoration:none;">'.self::e($footerEmail).'</a>' : '',
        ]);
        $footerAddress = (trim($d['footer_address']) !== '' ? '<p style="margin:4px 0 0;font-family:'.$font.';font-size:12.5px;line-height:19px;color:#6b635d;">'.self::lines($d['footer_address']).'</p>'."\n" : '')
            .($contactBits ? '<p style="margin:4px 0 0;font-family:'.$font.';font-size:12.5px;line-height:19px;color:#6b635d;">'.implode('&nbsp;&nbsp;|&nbsp;&nbsp;', $contactBits).'</p>'."\n" : '');
        $siteLabel = self::e(preg_replace('~^https?://(www\.)?|/$~i', '', $site));

        return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<title>'.self::e($d['heading'] !== '' ? $d['heading'] : $d['company']).'</title>
<style>
  body { margin:0; padding:0; width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
  table { border-collapse:collapse; mso-table-lspace:0; mso-table-rspace:0; }
  img { -ms-interpolation-mode:bicubic; }
  @media only screen and (max-width:620px) {
    .wrap { width:100% !important; }
    .px { padding-left:20px !important; padding-right:20px !important; }
    .h1 { font-size:22px !important; line-height:29px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:'.$bg.';">
'.(trim($d['preheader']) !== '' ? '<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:'.$bg.';opacity:0;">'.self::e($d['preheader']).'</div>'."\n" : '').'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="'.$bg.'" style="background-color:'.$bg.';">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:600px;max-width:600px;background-color:#ffffff;border:1px solid #e4e1dc;">
'.$header.'
'.$heroRow.'
<tr><td class="px" style="padding:32px 36px 28px;">
'.(trim($d['heading']) !== '' ? '<h1 class="h1" style="margin:0 0 16px;font-family:'.$font.';font-size:25px;line-height:32px;font-weight:bold;color:#1c1917;">'.self::e($d['heading']).'</h1>'."\n" : '')
.self::emailBody($d['body'], $font, $accent).'
'.$buttonRow.$signoff.'
</td></tr>
'.$bannerRow('middle').$bannerRow('bottom').'<tr><td class="px" align="center" bgcolor="#faf9f7" style="background-color:#faf9f7;border-top:1px solid #e4e1dc;padding:22px 36px;">
'.($socials !== '' ? $socials.'<div style="height:12px;line-height:12px;font-size:0;">&nbsp;</div>'."\n" : '')
.'<p style="margin:0;font-family:'.$font.';font-size:12.5px;line-height:19px;font-weight:bold;color:#57534e;">'.$footerText.'</p>
'.$footerAddress.($site !== '' ? '<p style="margin:4px 0 0;font-family:'.$font.';font-size:12.5px;line-height:19px;"><a href="'.self::e($site).'" target="_blank" style="color:'.$accent.';text-decoration:none;">'.$siteLabel.'</a></p>'."\n" : '')
.(trim($d['footer_note']) !== '' ? '<p style="margin:12px 0 0;font-family:'.$font.';font-size:11px;line-height:16px;color:#8a827b;">'.self::lines($d['footer_note']).'</p>'."\n" : '')
.'</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }

    /** A square JPEG cut from the middle of an uploaded photo, for the signature headshot. Null when it cannot be read. */
    public static function squarePhoto(string $sourcePath, int $side = 240, ?string $fill = null): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($image = @imagecreatefromstring((string) @file_get_contents($sourcePath)))) {
            return null;
        }
        [$w, $h] = [imagesx($image), imagesy($image)];
        $crop = min($w, $h);
        $out = imagecreatetruecolor($side, $side);
        // A cut-out photo (transparent PNG) gets the chosen colour behind it; white when none is picked.
        [$r, $g, $b] = sscanf(Branding::validHex($fill) ?: '#ffffff', '#%02x%02x%02x');
        imagefill($out, 0, 0, imagecolorallocate($out, $r, $g, $b));
        imagecopyresampled($out, $image, 0, 0, (int) (($w - $crop) / 2), (int) (($h - $crop) / 3), $side, $side, $crop, $crop);
        ob_start();
        imagejpeg($out, null, 86);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($out);

        return $bytes ?: null;
    }
}
