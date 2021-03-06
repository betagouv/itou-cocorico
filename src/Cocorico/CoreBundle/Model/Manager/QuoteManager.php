<?php

/*
 * This file is part of the Cocorico package.
 *
 * (c) Cocolabs SAS <contact@cocolabs.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cocorico\CoreBundle\Model\Manager;

use Cocorico\CoreBundle\Entity\Quote;
use Cocorico\CoreBundle\Entity\Listing;
use Cocorico\CoreBundle\Entity\ListingDiscount;
use Cocorico\CoreBundle\Event\QuoteEvent;
use Cocorico\CoreBundle\Event\QuoteEvents;
use Cocorico\CoreBundle\Mailer\TwigSwiftMailer;
use Cocorico\CoreBundle\Repository\QuoteRepository;
use Cocorico\CoreBundle\Repository\ListingDiscountRepository;
use Cocorico\SMSBundle\Twig\TwigSmser;
use Cocorico\TimeBundle\Model\DateTimeRange;
use Cocorico\TimeBundle\Model\TimeRange;
use Cocorico\UserBundle\Entity\User;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Cocorico\CoreBundle\Utils\Tracker;

class QuoteManager extends BaseManager
{
    protected $em;
    protected $dm;
    protected $availabilityManager;
    protected $mailer;
    protected $smser;
    protected $dispatcher;
    protected $defaultListingStatus;
    protected $bundles;
    public $maxPerPage;
    protected $tracker;

    /**
     * @param EntityManager              $em
     * @param TwigSwiftMailer            $mailer
     * @param TwigSmser|null             $smser
     * @param EventDispatcherInterface   $dispatcher
     * @param array                      $parameters
     *        float     $feeAsAsker
     *        float     $feeAsOfferer
     *        boolean   $endDayIncluded
     *        int       $timeUnit App time unit includeVat
     *        int       $timesMax Max times unit if time_unit includeVat
     *        array     $hoursAvailable
     *        int       $minStartTimeDelay
     *        int       $minPrice
     *        int       $maxPerPage
     *        int       $defaultListingStatus
     *        float     $vatRate
     *        bool      $includeVat
     *
     * todo: decouple sms bundle by dispatching event each time is used
     */
    public function __construct(
        EntityManager $em,
        TwigSwiftMailer $mailer,
        $smser,
        EventDispatcherInterface $dispatcher,
        $parameters
    ) {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->smser = $smser;
        $this->dispatcher = $dispatcher;

        //Parameters
        $parameters = $parameters["parameters"];

        $this->maxPerPage = $parameters["cocorico_dashboard_max_per_page"];
        $this->defaultListingStatus = $parameters["cocorico_listing_availability_status"];
        $this->bundles = $parameters["cocorico_bundles"];
        $this->tracker = new Tracker($_SERVER['ITOU_ENV'], "test");
    }

    /**
     * Pre-set new Quote based data.
     *
     * @param Listing       $listing
     * @param User|null     $user
     * @param DateTimeRange $dateTimeRange
     * @return Quote
     */
    public function initQuote(Listing $listing, $user)
    {
        $quote = new Quote();
        $quote->setListing($listing);
        $quote->setUser($user);
        $quote->setStatus(Quote::STATUS_DRAFT);

        return $quote;
    }

    /**
     * notify regular quote
     *
     * @param Listing       $listing
     * @param String        $stype
     * @return void
     */
    public function notifyQuote(Quote $quote, string $type)
    {
    switch($type) {
        case "ask-flash-demand":
            $this->mailer->sendNewFlashQuoteToAsker($quote);
            break;
        case "ask-demand":
            $this->mailer->sendNewQuoteToAsker($quote);
            break;
        case "off-notif":
            $this->mailer->sendNewQuoteToOfferer($quote);
            break;
        case "off-msg":
            $this->mailer->sendQuoteMessageToOfferer($quote);
            break;
        case "ask-msg":
            $this->mailer->sendQuoteMessageToAsker($quote);
            break;
        }
    }


    /**
     * @param int    $askerId
     * @param string $locale
     * @param int    $page
     * @param array  $status
     *
     * @return Paginator
     */
    public function findByAsker($askerId, $locale, $page, $status = array())
    {
        $queryBuilder = $this->getRepository()->getFindByAskerQuery($askerId, $locale, $status);

        //Pagination
        $queryBuilder
            ->setFirstResult(($page - 1) * $this->maxPerPage)
            ->setMaxResults($this->maxPerPage);

        //Query
        $query = $queryBuilder->getQuery();

        return new Paginator($query);
    }

    /**
     * @param  int    $offererId
     * @param  string $locale
     * @param  int    $page
     * @param  array  $status
     * @return Paginator
     */
    public function findByOfferer($offererId, $locale, $page, $status = array())
    {
        $queryBuilder = $this->getRepository()->getFindByOffererQuery($offererId, $locale, $status);

        //Pagination
        $queryBuilder
            ->setFirstResult(($page - 1) * $this->maxPerPage)
            ->setMaxResults($this->maxPerPage);

        //Query
        $query = $queryBuilder->getQuery();

        return new Paginator($query);
    }

    /**
     * @param int    $id
     * @param int    $askerId
     * @param string $locale
     * @param array  $status
     *
     * @return Quote|null
     *
     * @throws NonUniqueResultException
     */
    public function findOneByAsker($id, $askerId, $locale, $status = array())
    {
        $queryBuilder = $this->getRepository()->getFindOneByAskerQuery($id, $askerId, $locale, $status);

        $query = $queryBuilder->getQuery();

        return $query->getOneOrNullResult();
    }


    /**
     * Create a new quote
     *
     * @param Quote $quote
     * @return Quote|false
     */
    public function create(Quote $quote)
    {
        if (in_array($quote->getStatus(), Quote::$newableStatus)) {
            //New Quote confirmation
            $quote->setStatus(Quote::STATUS_NEW);

            $quote->setTimeZoneAsker($quote->getUser()->getTimeZone());
            $quote->setTimeZoneOfferer($quote->getListing()->getUser()->getTimeZone());

            $quote = $this->save($quote);

            // TODO: Send mail to offerer
            // $this->mailer->sendQuoteRequestMessageToOfferer($quote);
            // $this->mailer->sendQuoteRequestMessageToAsker($quote);

            if ($this->smser) {
                $this->smser->sendQuoteRequestMessageToOfferer($quote);
            }

            $this->tracker->track('backend', 'quote_new', array(
                'id' => $quote->getId(),
                'id_annonce' => $quote->getListing()->getId(),
                'fournisseur' => $quote->getListing()->getUser()->getCompanyName(),
                'demandeur' => $quote->getUser()->getCompanyName(),
            ));

            return $quote;
        }

        return false;
    }

    /**
     * Return whether a quote can be canceled by asker
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canBeCanceledByAsker(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$cancelableStatus);

        return $statusIsOk;
    }

    /**
     * Return whether a quote can be canceled by asker
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canBeAcceptedByAsker(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$acceptableStatus);

        return $statusIsOk;
    }


    /**
     * Return whether a quote can be accepted or refused by offerer
     * A quote can be accepted or refused no later than $acceptationDelay hours before it starts
     * and no later than $expirationDelay hours after new quote request date
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canBeAcceptedOrRefusedByOfferer(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$cancelableStatus);

        return $statusIsOk;
        /*
        $isNotExpired = $quote->getTimeBeforeExpiration(
            $this->expirationDelay,
            $this->acceptationDelay
        );
        $isNotExpired = $isNotExpired && $isNotExpired > 0;

        return $statusIsOk && $isNotExpired;
        */
    }
    /**
     * Return whether a quote can be refused by offerer
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canBeRefusedByOfferer(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$refusableOffererStatus);

        return $statusIsOk;
    }
    /**
     * Return whether a quote can be refused by asker
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canBeRefusedByAsker(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$refusableAskerStatus);

        return $statusIsOk;
    }

    /**
     * Return whether a prequote demand can be accepted by offerer
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function preQuoteCanBeAccepted(Quote $quote)
    {
        return $quote->getStatus() == Quote::STATUS_NEW;
    }
    /**
     * Return whether contact info can be shown
     *
     * @param Quote $quote
     *
     * @return bool
     */
    public function canShowContactInfo(Quote $quote)
    {
        $statusIsOk = in_array($quote->getStatus(), Quote::$canShowContactInfo);

        return $statusIsOk;
    }

    /**
     * Asker accept quote :
     *
     * @param Quote $quote
     *
     * @return Quote|bool
     */
    public function accept(Quote $quote)
    {
        if ($this->canBeAcceptedByAsker($quote)) {
            $quote->setStatus(Quote::STATUS_ACCEPTED);
            $quote = $this->save($quote);

            // Update offerer accepted quotes
            $offerer = $quote->getListing()->getUser();
            $offerer->setNbQuotesOfferer($offerer->getNbQuotesOfferer() + 1);
            $this->em->persist($offerer);
            $this->em->flush();

            // Update asker accepted quotes
            $asker = $quote->getUser();
            $asker->setNbQuotesAsker($asker->getNbQuotesAsker() + 1);
            $this->em->persist($asker);
            $this->em->flush();

            //TODO: Add mailer events for quote acceptation
            return $quote;
        }
        return false;
    }

    /**
     * Offerer accepts prequote discussion\ :
     *
     * @param Quote $quote
     *
     * @return Quote|bool
     */
    public function accept_prequote(Quote $quote)
    {
        if ($this->canBeRefusedByOfferer($quote)) {
            $quote->setStatus(Quote::STATUS_PREQUOTE);
            $quote = $this->save($quote);
            //TODO: Add mailer events for quote sent

            $this->tracker->track('backend', 'metric_userlink', array(
                'id' => $quote->getId(),
                'id_annonce' => $quote->getListing()->getId(),
                'fournisseur' => $quote->getListing()->getUser()->getCompanyName(),
                'demandeur' => $quote->getUser()->getCompanyName(),
            ));
            return $quote;
        }
        return false;
    }

    /**
     * Offerer sent quote :
     *
     * @param Quote $quote
     *
     * @return Quote|bool
     */
    public function sent_quote(Quote $quote)
    {
        if ($this->canBeRefusedByOfferer($quote)) {
            $quote->setStatus(Quote::STATUS_QUOTE);
            $quote = $this->save($quote);
            //TODO: Add mailer events for quote sent
            return $quote;
        }
        return false;
    }

    /**
     * Offerer refuse quote :
     *  Set quote status as refused
     *  Send mails
     *
     * @param Quote $quote
     * @param bool    $refusedByOfferer
     *
     * @return Quote|bool
     */
    public function refuse(Quote $quote, $refusedByOfferer = true)
    {
        $byOfferer = $this->canBeRefusedByOfferer($quote);
        $byAsker = $this->canBeRefusedByAsker($quote);
        if ($byOfferer or $byAsker) {
            if ($byOfferer and $refusedByOfferer) {
                $quote->setStatus(Quote::STATUS_REFUSED_OFFERER);
                // TODO: send mail
                }
            else if ($byAsker and ! $refusedByOfferer) {
                $quote->setStatus(Quote::STATUS_REFUSED_ASKER);
                // TODO: send mail
                }
            $quote = $this->save($quote);
            return $quote;
        }
        /*
        if ($byOfferer or $byAsker) {
            $quote->setStatus(Quote::STATUS_REFUSED);
            $quote->setRefusedQuoteAt(new DateTime());
            $quote = $this->save($quote);

            $this->mailer->sendQuoteRefusedMessageToAsker($quote);
            if ($refusedByOfferer) {
                $this->mailer->sendQuoteRefusedMessageToOfferer($quote);
            }

            if ($this->smser) {
                $this->smser->sendQuoteRefusedMessageToAsker($quote);
            }

            return $quote;
        }
        */

        return false;
    }

    /**
     * Asker cancel quote.
     *  There are two cases:
     *      Either the quote has not been accepted by the offerer and so not already payed. Its status is new and
     *          no refund need to be made.
     *      Either quote status is payed and is not already validated. In this case the funds are in the asker wallet
     *      and must be refunded to his bank account.
     *
     *
     * Operations:
     *  Optionally refund asker
     *  Set quote status as cancel
     *  Send mails
     *
     * @param Quote $quote
     *
     * @return Quote|bool
     */
    public function cancel(Quote $quote)
    {
        if ($this->canBeCanceledByAsker($quote)) {
            $cancelable = false;

            if ($quote->getStatus() == Quote::STATUS_NEW) {
                $cancelable = true;
            }

            if ($cancelable) {
                $quote->setStatus(Quote::STATUS_CANCELED);
                $quote->setCanceledQuoteAt(new DateTime());
                $quote = $this->save($quote);

                // $this->mailer->sendQuoteCanceledByAskerMessageToAsker($quote);
                // $this->mailer->sendQuoteCanceledByAskerMessageToOfferer($quote);

                if ($this->smser) {
                    $this->smser->sendQuoteCanceledByAskerMessageToOfferer($quote);
                }


                return $quote;
            }
        }

        return false;
    }

    /**
     * @param Quote $quote
     * @return Quote
     */
    public function save(Quote $quote)
    {
        $this->persistAndFlush($quote);

        return $quote;
    }

    /**
     * @return TwigSwiftMailer
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     *
     * @return QuoteRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('CocoricoCoreBundle:Quote');
    }

    /**
     *
     * @return ListingAvailabilityRepository
     */
    protected function getAvailabilityRepository()
    {
        return false;
    }

    /**
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }
}
