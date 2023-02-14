<?php

use PHPUnit\Framework\TestCase;
use Gitplus\PayFluid\Customization;
class CustomizationTest extends TestCase
{
    public function testEditAmount()
    {
        $c = new Customization();
        $key = "editAmt";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertFalse($c->toArray()[$key]);

        $c->editAmount(true);
        $this->assertTrue($c->toArray()[$key]);
    }


    public function testMinimumAmount()
    {
        $customization = new Customization();
        $minAmt = "minAmt";
        $this->assertArrayHasKey($minAmt, $customization->toArray());
        $this->assertEmpty($customization->toArray()[$minAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$minAmt]);

        $customization->minimumAmount(2.5);
        $this->assertEquals(2.5, $customization->toArray()[$minAmt]);
    }
    public function testMaximumAmount()
    {
        $customization = new Customization();
        $maxAmt = "maxAmt";
        $this->assertArrayHasKey($maxAmt, $customization->toArray());
        $this->assertEmpty($customization->toArray()[$maxAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$maxAmt]);

        $customization->maximumAmount(2.5);
        $this->assertEquals(2.5, $customization->toArray()[$maxAmt]);
    }

    public function testMaximumAmountCannotBeLessThanMinimumAmount()
    {
        $customization = new Customization();
        $maxAmt = "maxAmt";
        $minAmt = "minAmt";
        $this->assertArrayHasKey($maxAmt, $customization->toArray());
        $this->assertArrayHasKey($minAmt, $customization->toArray());

        $this->assertEmpty($customization->toArray()[$maxAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$maxAmt]);

        $this->assertEmpty($customization->toArray()[$minAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$minAmt]);

        $this->expectException(Exception::class);
        $customization->maximumAmount(3);
        $customization->minimumAmount(5.0);
    }

    public function testMinimumAmountCannotBeGreaterThanMaximumAmount()
    {
        $customization = new Customization();
        $maxAmt = "maxAmt";
        $minAmt = "minAmt";
        $this->assertArrayHasKey($maxAmt, $customization->toArray());
        $this->assertArrayHasKey($minAmt, $customization->toArray());

        $this->assertEmpty($customization->toArray()[$maxAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$maxAmt]);

        $this->assertEmpty($customization->toArray()[$minAmt]);
        $this->assertEquals(0.0, $customization->toArray()[$minAmt]);

        $this->expectException(Exception::class);
        $customization->minimumAmount(5.0);
        $customization->maximumAmount(3);
    }

    public function testBorderTheme()
    {
        $c = new Customization();
        $key = "borderTheme";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()["borderTheme"]);

        $c->borderTheme("#aa33ff");
        $this->assertEquals("#aa33ff", $c->toArray()["borderTheme"]);
    }

    public function testInvalidBorderTheme()
    {
        $c = new Customization();
        $this->expectException(Exception::class);
        $c->borderTheme("invald#hex");
    }

    public function testReceiptFeedbackPhone()
    {
        $c = new Customization();
        $key = "receiptFeedbackPhone";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $c->receiptFeedbackPhone("0241111111");
        $this->assertEquals("0241111111", $c->toArray()[$key]);

        $c->receiptFeedbackPhone("  0241111111");
        $this->assertEquals("0241111111", $c->toArray()[$key]);

        $c->receiptFeedbackPhone("+233241111111");
        $this->assertEquals("+233241111111", $c->toArray()[$key]);
    }

    public function testReceiptFeedbackPhoneLength()
    {
        $c = new Customization();
        $key = "receiptFeedbackPhone";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $this->expectException(Exception::class);
        $c->receiptFeedbackPhone("026111111");
    }

    public function testPhoneIsNumeric()
    {
        $c = new Customization();
        $key = "receiptFeedbackPhone";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $this->expectException(Exception::class);
        $c->receiptFeedbackPhone("A202323");
    }

    public function testReceiptEmail()
    {
        $c = new Customization();
        $key = "receiptFeedbackEmail";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $c->receiptFeedbackEmail("janedoe@email.com");
        $this->assertEquals("janedoe@email.com", $c->toArray()[$key]);

        $c->receiptFeedbackEmail("   john@email.com   \n\t");
        $this->assertEquals("john@email.com", $c->toArray()[$key]);
    }

    public function testInvalidReceiptEmail()
    {
        $c = new Customization();
        $key = "receiptFeedbackEmail";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $this->expectException(Exception::class);
        $c->receiptFeedbackEmail("janedoe@emailcom");
        $c->receiptFeedbackEmail("   johnemail.com   \n\t");
    }

    public function testDaysUntilLinkExpires()
    {
        $c = new Customization();
        $key = "payLinkExpiryInDays";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals(3, $c->toArray()[$key]);

        $c->daysUntilLinkExpires(15);
        $this->assertEquals(15, $c->toArray()[$key]);
    }

    public function testDisplayPicture()
    {
        $c = new Customization();
        $key = "displayPicture";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $c->displayPicture("https://i.pravatar.cc/300");
        $this->assertEquals("https://i.pravatar.cc/300", $c->toArray()[$key]);
    }

    public function testAssertInvalidPictureUrl()
    {
        $c = new Customization();
        $key = "displayPicture";
        $this->assertArrayHasKey($key, $c->toArray());
        $this->assertEquals("", $c->toArray()[$key]);

        $this->expectException(Exception::class);
        $c->displayPicture("https:/i.pravatar.cc/300");
        $c->displayPicture("https:i.pravatar.cc/300");
        $c->displayPicture("https//i.pravatar.cc/300");
    }

}