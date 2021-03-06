<?php

/*
 * This file is part of the Cocorico package.
 *
 * (c) Cocolabs SAS <contact@cocolabs.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cocorico\MessageBundle\Entity;

use Cocorico\CoreBundle\Entity\Booking;
use Cocorico\CoreBundle\Entity\Quote;
use Cocorico\CoreBundle\Entity\Listing;
use Cocorico\CoreBundle\Model\BaseBooking;
use Doctrine\ORM\Mapping as ORM;
use FOS\MessageBundle\Entity\Thread as BaseThread;

/**
 * @ORM\Entity(repositoryClass="Cocorico\MessageBundle\Repository\ThreadRepository")
 *
 * @ORM\Table(name="message_thread")
 */
class Thread extends BaseThread
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Cocorico\UserBundle\Entity\User")
     */
    protected $createdBy;

    /**
     * @ORM\OneToMany(
     *   targetEntity="Cocorico\MessageBundle\Entity\Message",
     *   mappedBy="thread"
     * )
     * @var Message[]|\Doctrine\Common\Collections\Collection
     */
    protected $messages;

    /**
     * @ORM\OneToMany(
     *   targetEntity="Cocorico\MessageBundle\Entity\ThreadMetadata",
     *   mappedBy="thread",
     *   cascade={"all"}
     * )
     * @var ThreadMetadata[]|\Doctrine\Common\Collections\Collection
     */
    protected $metadata;

    /**
     * @ORM\ManyToOne(targetEntity="\Cocorico\CoreBundle\Entity\Listing", inversedBy="threads")
     * @ORM\JoinColumn(name="listing_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $listing;

    /**
     * @ORM\OneToOne(targetEntity="\Cocorico\CoreBundle\Entity\Booking", inversedBy="thread")
     * @ORM\JoinColumn(name="booking_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $booking;

    /**
     * @ORM\OneToOne(targetEntity="\Cocorico\CoreBundle\Entity\Quote", inversedBy="thread")
     * @ORM\JoinColumn(name="quote_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $quote;

    /**
     * @return Listing
     */
    public function getListing()
    {
        return $this->listing;
    }

    /**
     * @param Listing $listing
     * @return $this
     */
    public function setListing(Listing $listing)
    {
        $this->listing = $listing;

        return $this;
    }

    /**
     * @return Booking
     */
    public function getBooking()
    {
        return $this->booking;
    }

    /**
     * @param  BaseBooking $booking
     * @return $this
     */
    public function setBooking(BaseBooking $booking = null)
    {
        $this->booking = $booking;

        return $this;
    }

    /**
     * @return Quote
     */
    public function getQuote()
    {
        return $this->quote;
    }

    /**
     * @param  Quote $quote
     * @return $this
     */
    public function setQuote(Quote $quote = null)
    {
        $this->quote = $quote;

        return $this;
    }

    public function __toString()
    {
        return "" . $this->getId();
    }

    /**
     * @param Message $message
     */
    public function removeMessage(Message $message)
    {
        $this->messages->removeElement($message);
    }



    /**
     * Add metadata.
     *
     * @param \Cocorico\MessageBundle\Entity\ThreadMetadata $metadata
     *
     * @return Thread
     */
    public function addMetadatum(\Cocorico\MessageBundle\Entity\ThreadMetadata $metadata)
    {
        $this->metadata[] = $metadata;

        return $this;
    }

    /**
     * Remove metadata.
     *
     * @param \Cocorico\MessageBundle\Entity\ThreadMetadata $metadata
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeMetadatum(\Cocorico\MessageBundle\Entity\ThreadMetadata $metadata)
    {
        return $this->metadata->removeElement($metadata);
    }

    /**
     * Get metadata.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMetadata()
    {
        return $this->metadata;
    }
}
