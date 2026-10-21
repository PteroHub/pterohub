<?php
namespace App\Http\Controllers;

use App\Mail\PterohubTicketMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

final class PterohubTicketsController extends Controller
{
    private function guard(): void { abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403); }
    private function notifyCustomer(int $userId, int $ticketId, string $subject, string $message): void
    {
        if (!config('pterohub_notifications.ticket_emails', false)) return;
        $email = DB::table('users')->where('id', $userId)->value('email');
        if (!$email) return;
        try {
            Mail::to($email)->queue(new PterohubTicketMail($ticketId, $subject, $message));
        } catch (Throwable $e) {
            Log::warning('PteroHub ticket email queue failed', ['ticket_id'=>$ticketId, 'exception'=>get_class($e)]);
        }
    }
    public function index(Request $request)
    {
        $this->guard();
        $q = DB::table('tickets as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->select('t.*', 'u.name as customer_name', 'u.email as customer_email');
        if ($request->filled('status') && in_array($request->query('status'), ['open','closed'], true)) $q->where('t.status', $request->query('status'));
        if (trim((string)$request->query('q','')) !== '') {
            $term = trim((string)$request->query('q'));
            $q->where(function($sub) use ($term) { $sub->where('t.subject','like','%'.$term.'%')->orWhere('u.email','like','%'.$term.'%'); });
        }
        return view('pterohub.tickets.index', ['tickets'=>$q->orderByDesc('t.id')->paginate(20)->withQueryString()]);
    }
    public function create()
    {
        $this->guard();
        return view('pterohub.tickets.create', ['customers'=>DB::table('users')->select('id','name','email')->orderBy('name')->limit(500)->get()]);
    }
    public function store(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'user_id'=>'required|integer|exists:users,id', 'subject'=>'required|string|min:3|max:255',
            'body'=>'required|string|min:2|max:10000', 'department'=>['required',Rule::in(['support','billing','technical'])],
            'priority'=>['required',Rule::in(['low','normal','high'])],
        ]);
        $ticketId = DB::transaction(function() use ($data) {
            $id = DB::table('tickets')->insertGetId(['user_id'=>$data['user_id'],'subject'=>$data['subject'],
                'department'=>$data['department'],'priority'=>$data['priority'],'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
            DB::table('ticket_messages')->insert(['ticket_id'=>$id,'user_id'=>auth()->id(),'body'=>$data['body'],
                'is_internal'=>false,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        });
        $this->notifyCustomer((int)$data['user_id'], $ticketId, $data['subject'], 'A support ticket has been opened for your account.');
        return redirect()->route('pterohub.tickets.show',$ticketId)->with('ok','Ticket created.');
    }
    public function show(int $id)
    {
        $this->guard();
        $ticket = DB::table('tickets as t')->join('users as u','u.id','=','t.user_id')
            ->select('t.*','u.name as customer_name','u.email as customer_email')->where('t.id',$id)->first();
        abort_unless($ticket,404);
        $messages = DB::table('ticket_messages as m')->join('users as u','u.id','=','m.user_id')
            ->select('m.*','u.name as author_name')->where('m.ticket_id',$id)->orderBy('m.id')->get();
        return view('pterohub.tickets.show',compact('ticket','messages'));
    }
    public function reply(Request $request, int $id)
    {
        $this->guard();
        $data=$request->validate(['body'=>'required|string|min:2|max:10000','is_internal'=>'nullable|boolean']);
        $ticket=DB::table('tickets')->where('id',$id)->first();abort_unless($ticket,404);
        abort_if($ticket->status==='closed',422,'Reopen this ticket before replying.');
        DB::transaction(function() use($id,$data) {
            DB::table('ticket_messages')->insert(['ticket_id'=>$id,'user_id'=>auth()->id(),
                'body'=>$data['body'],'is_internal'=>(bool)($data['is_internal']??false),
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('tickets')->where('id',$id)->update(['updated_at'=>now()]);
        });
        if (empty($data['is_internal'])) $this->notifyCustomer((int)$ticket->user_id,$id,$ticket->subject,'There is a new reply on your support ticket.');
        return redirect()->route('pterohub.tickets.show',$id)->with('ok','Reply saved.');
    }
    public function status(Request $request,int $id)
    {
        $this->guard();$data=$request->validate(['status'=>['required',Rule::in(['open','closed'])]]);
        abort_unless(DB::table('tickets')->where('id',$id)->exists(),404);
        DB::table('tickets')->where('id',$id)->update(['status'=>$data['status'],'updated_at'=>now()]);
        return redirect()->route('pterohub.tickets.show',$id)->with('ok','Status updated.');
    }
}
