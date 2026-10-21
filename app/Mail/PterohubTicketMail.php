<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PterohubTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public function __construct(public int $ticketId, public string $ticketSubject, public string $messageText) {}
    public function build(): static
    {
        return $this->subject('PteroHub Support — Ticket #'.$this->ticketId)
           ->view('pterohub.email.ticket-notification')
           ->with(['ticketId'=>$this->ticketId,'ticketSubject'=>$this->ticketSubject,'messageText'=>$this->messageText]);
    }
}
