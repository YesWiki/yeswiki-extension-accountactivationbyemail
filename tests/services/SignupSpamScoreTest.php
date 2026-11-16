<?php

namespace YesWiki\Test\Accountactivationbyemail\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Accountactivationbyemail\Service\SignupSpamScore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Accounts that signed up on ferme.yeswiki.net in October and November 2026, robots and people. */
class SignupSpamScoreTest extends YesWikiTestCase
{
    public static function robots(): array
    {
        return [
            ['tk88cards1', 'vinhthuongchu3@gmail.com'],
            ['uu88apporg2', 'nhattructai@gmail.com'],
            ['onejd88com1', 'limai02727u@gmail.com'],
            ['uy88buzz', 'tska11216@gmail.com'],
            ['kp88bio3', 'trieuvankhuc8@gmail.com'],
            ['sc88bncom', 'nuxollbracey@gmail.com'],
            ['v79social', 'hanhsiu2992@gmail.com'],
            ['e39social2', 'pat.williams998701@gmail.com'],
            ['e39evcom', 'jkliiupvuikmnbasd@gmail.com'],
            ['e39bmwcom', 'trinhconggy345@gmail.com'],
            ['e39fcom', 'duongluctran25@gmail.com'],
            ['shbetinfo', 'duynha00@gmail.com'],
            ['shbethealth2', 'gillinsmennecke@gmail.com'],
            ['shbetlaw1', 'tranlao93775@gmail.com'],
            ['soicaulodechuannet2', 'myrticeburdette0317@gmail.com'],
            ['rhinobetting', 'rhinobettingg@gmail.com'],
            ['kolkatafatafat', 'yidicac303@macsnipe.com'],
            ['colatvchannel', 'halleenazim@gmail.com'],
            ['cakhiatvcio3', 'trinhlucdao@gmail.com'],
            ['uktvhub', 'iptvukme@gmail.com'],
            ['IptvReselleruk', 'iptvresellerukstore@gmail.com'],
            ['snowdaycalculators', 'xowade4415@playfine.com'],
            ['CGEscort1425', 'rikese9605@micropaste.com'],
            ['cacej66920@iconcubic.com', 'cacej66920@iconcubic.com'],
            ['bryonzxferrykazcl11@gmail.com', 'bryonzxferrykazcl11@gmail.com'],
        ];
    }

    public static function strangersAddresses(): array
    {
        return [
            ['BerthaGwinn', 'martarobertson126@gmail.com'],
            ['dovesovn', 'nguyenhoa1991a12@gmail.com'],
            ['link', 'bl1626697835@outlook.com'],
            ['aerynwatts', 'muzammilzahoor053+yeswiki@gmail.com'],
            ['EvanReed', 'tianwei3333@agent.qq.com'],
            ['Maraappel', 'sallyanne1466@fldubai.com'],
            ['coolgames', 'uronpen19802026@2925.com'],
        ];
    }

    public static function people(): array
    {
        return [
            ['Virginie', 'virgin170900@gmail.com'],
            ['Raphael', 'raphaelmaille559@gmail.com'],
            ['Nota bene', 'noemiebode1@gmail.com'],
            ['Max Adear 09', 'maxime.moine@adear09.org'],
            ['Adear09', 'adear.09@orange.fr'],
            ['Luc69', 'lucdischert@free.fr'],
            ['JP_F', 'jpf58@9online.fr'],
            ['flox', 'flox033@gmail.com'],
            ['MathildeBlondel', 'solad@udess05.org'],
            ['Maxime GOUTAGNEUX', 'maxime.goutagneux@annonayrhoneagglo.fr'],
            ['CdI Fondazione Felice', 'cio@fondazionefelice.org'],
            ['Salve a Democracia', 'salveademocracia@proton.me'],
            ['GEM PASSE MURAILLE', 'legempassemuraille.animateur@yahoo.fr'],
            ['Gaëlle Élise', 'gaelle.elise1984@laposte.net'],
            ['amap88', 'amap88@orange.fr'],
            ['amap44nantes', 'contact@amap44.org'],
            ['jardin2pain', 'jardin2pain@gmail.com'],
            ['Bastien2026', 'bastien.roux2026@gmail.com'],
        ];
    }

    #[DataProvider('robots')]
    public function testARobotIsRefused(string $name, string $email)
    {
        $verdict = $this->score()->of($name, $email);

        $this->assertTrue($this->score()->isSpam($verdict), "$name scored {$verdict['score']} (" . implode(',', $verdict['reasons']) . ')');
    }

    #[DataProvider('strangersAddresses')]
    public function testAStrangersAddressIsOnlyFlagged(string $name, string $email)
    {
        $verdict = $this->score()->of($name, $email);

        $this->assertContains('mismatch', $verdict['reasons']);
        $this->assertFalse($this->score()->isSpam($verdict));
    }

    #[DataProvider('people')]
    public function testAPersonSignsUp(string $name, string $email)
    {
        $verdict = $this->score()->of($name, $email);

        $this->assertFalse($this->score()->isSpam($verdict), "$name scored {$verdict['score']} (" . implode(',', $verdict['reasons']) . ')');
    }

    public function testAStrangersAddressBecomesSpamOnceUntouchedForAWeek()
    {
        $user = ['name' => 'BerthaGwinn', 'email' => 'martarobertson126@gmail.com', 'signuptime' => '2026-10-21 13:57:01'];

        $this->assertFalse($this->score()->isSpam($this->score()->ofAccount($user, 0, strtotime('2026-10-25'))));
        $this->assertTrue($this->score()->isSpam($this->score()->ofAccount($user, 0, strtotime('2026-10-29'))));
        $this->assertFalse($this->score()->isSpam($this->score()->ofAccount($user, 3, strtotime('2026-10-29'))));
    }

    private function score(): SignupSpamScore
    {
        return $this->getWiki()->services->get(SignupSpamScore::class);
    }
}
