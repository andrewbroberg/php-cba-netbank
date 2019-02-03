<?php

namespace Kravock\Netbank;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\InputFormField;

class API
{
    private $username = '';
    private $password = '';
    private $client;
    private $guzzleClient;
    private $timezone = 'Australia/Sydney';

    const BASE_URL = 'https://www.my.commbank.com.au/';
    const LOGIN_URL = 'netbank/Logon/Logon.aspx';

    /**
     * Create a new API Instance
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->guzzleClient = new GuzzleClient(array(
            'allow_redirects' => true,
            'timeout' => 60,
            'cookies' => true,
            'headers' => [
                'User-Agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.1 Safari/537.36"            ]
        ));

        $this->client->setClient($this->guzzleClient);
    }

    public function login($username, $password)
    {
        $crawler = $this->client->request('GET', sprintf("%s%s", self::BASE_URL, self::LOGIN_URL));

        $form = $crawler->selectButton('Log on')->form();

        $fields = $crawler->filter('input');

        // We need to set fields to enabled otherwise we can't login
        foreach ($fields as $field) {
            $field->removeAttribute('disabled');
        }

        $form['txtMyClientNumber$field'] = $username;
        $form['txtMyPassword$field'] = $password;
        $form['JS'] = 'E';

        $crawler = $this->client->submit($form);

        $accountList = [];

        $crawler->filter('.main_group_account_row')->each(function ($account) use (&$accountList) {
            $name = $account;
            $name = $name->filter('.NicknameField a')->first();

            $bsb = $account;
            $bsb = $bsb->filter('.BSBField .field')->first();

            $accountNumber = $account;
            $accountNumber = $accountNumber->filter('.AccountNumberField .field')->first();

            $balance = $account;
            $balance = $balance->filter('td.AccountBalanceField span.Currency')->first();

            $available = $account;
            $available = $available->filter('td.AvailableFundsField span.Currency')->first();

            $bal = $balance->count() ? $balance->text() : 0;
            $avl = $available->count() ? $available->text() : 0;
            $bsb = $this->processNumbersOnly($bsb->count() ? $bsb->text() : '');
            $accountNumber = $this->processNumbersOnly($accountNumber->count() ? $accountNumber->text() : '');
            $fullAccountNumber = $bsb . $accountNumber;

            $accountList[$fullAccountNumber] = [
                'nickname' => $name->text(),
                'url' => $name->attr('href'),
                'bsb' => $bsb,
                'accountNum' => $accountNumber,
                'balance' => $this->processCurrency($bal),
                'available' => $this->processCurrency($avl)
            ];
        });

        if (!$accountList) {
            throw new \Exception('Unable to retrieve account list.');
        }

        return $accountList;
    }

    public function getTransactions($account, $from, $to)
    {
        $crawler = $this->getAccountPage($account);

        $form = $crawler->filter('#aspnetForm');

        // Check that we we a form on the transaction page
        if (!$form->count()) {
            return [];
        }

        $form = $form->form();

        $field = $this->createField('input', '__EVENTTARGET', 'ctl00$BodyPlaceHolder$lbSearch');
        $form->set($field);

        $field = $this->createField('input', '__EVENTARGUMENT', '');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$ctl00', 'ctl00$BodyPlaceHolder$updatePanelSearch|ctl00$BodyPlaceHolder$lbSearch');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$searchTypeField', '1');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$radioSwitchDateRange$field$', 'ChooseDates');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$dateRangeField', 'ChooseDates');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$fromCalTxtBox$field', $from);
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$toCalTxtBox$field', $to);
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$radioSwitchSearchType$field$', 'AllTransactions');
        $form->set($field);

        $crawler = $this->client->submit($form);

        return $this->filterTransactions($crawler);
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    private function processNumbersOnly($value)
    {
        return preg_replace('$[^0-9]$', '', $value);
    }

    private function processCurrency($amount)
    {
        $value = preg_replace('$[^0-9.]$', '', $amount);

        if (strstr($amount, 'DR')) {
            $value = -$value;
        }

        return $value;
    }

    private function createField($type, $name, $value)
    {
        $domdocument = new \DOMDocument;
        $ff = $domdocument->createElement($type);
        $ff->setAttribute('name', $name);
        $ff->setAttribute('value', $value);
        $formfield = new InputFormField($ff);

        return $formfield;
    }

    public function filterTransactions($crawler)
    {
        $pattern = '
        /
        \{              # { character
            (?:         # non-capturing group
                [^{}]   # anything that is not a { or }
                |       # OR
                (?R)    # recurses the entire pattern
            )*          # previous group zero or more times
        \}              # } character
        /x
        ';
        $html = $crawler->html();

        preg_match_all('/({"Transactions":(?:.+)})\);/', $html, $matches);

        foreach ($matches[1] as $_temp) {
            if (strstr($_temp, 'Transactions')) {
                $transactions = json_decode($_temp);
                break;
            }
        }

        $transactionList = [];
        if (!empty($transactions->Transactions)) {
            foreach ($transactions->Transactions as $transaction) {
                $date = \DateTime::createFromFormat('YmdHisu', substr($transaction->Date->Sort[1], 0, 20), new \DateTimeZone('UTC'));
                $date->setTimeZone(new \DateTimeZone($this->timezone));
                $transactionList[] = [
                    'timestamp' => $transaction->Date->Sort[1],
                    'date' => $date->format('Y-m-d H:i:s.u'),
                    'description' => $transaction->Description->Text,
                    'amount' => $this->processCurrency($transaction->Amount->Text),
                    'balance' => $this->processCurrency($transaction->Balance->Text),
                    'trancode' => $transaction->TranCode->Text,
                    'receiptnumber' => $transaction->ReceiptNumber->Text,
                ];
            }

        }

        return $transactionList;
    }

    private function getAccountPage($account)
    {
        $link = sprintf("%s%s", self::BASE_URL, $account['url']);
        return $this->client->request('GET', $link);
    }
}
