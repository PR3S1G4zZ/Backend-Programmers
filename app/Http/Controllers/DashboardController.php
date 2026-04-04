<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Project, Application, Message};
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function company(Request $r)
    {
        abort_unless($r->user()->user_type==='company', 403);
        $uid = $r->user()->id;
        $active = Project::where('company_id',$uid)->whereIn('status',['open','in_progress'])->count();
        $hired = Application::whereHas('project', fn($q)=>$q->where('company_id',$uid))
                  ->where('status','accepted')->count();
        $budget = Project::where('company_id', $uid)->get()->sum(function($project) {
            return $project->budget_max ?? $project->budget_min ?? 0;
        });
        $total = Project::where('company_id',$uid)->whereNotIn('status',['draft'])->count();
        $completed = Project::where('company_id',$uid)->where('status','completed')->count();
        $completion = $total > 0 ? round(($completed / $total) * 100) : 0;
        return compact('active','hired','budget','completion');
    }

    public function programmer(Request $r)
    {
        abort_unless($r->user()->user_type === 'programmer', 403);
        $user = $r->user();
        $uid = $user->id;

        // 1. Ganado este mes — PaymentService creates 'payment_received' transactions for devs
        $earningTypes = ['deposit', 'payment_received'];
        
        $earningsThisMonth = $user->wallet 
            ? $user->wallet->transactions()
                ->whereIn('type', $earningTypes)
                ->where('amount', '>', 0)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount')
            : 0;

        $previousMonthEarnings = $user->wallet
            ? $user->wallet->transactions()
                ->whereIn('type', $earningTypes)
                ->where('amount', '>', 0)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount')
            : 0;
            
        $earningsGrowth = $previousMonthEarnings > 0 
            ? (($earningsThisMonth - $previousMonthEarnings) / $previousMonthEarnings) * 100 
            : 0;

        // 2. Proyectos Activos
        $activeProjectsCount = Application::where('developer_id', $uid)
            ->where('status', 'accepted')
            ->whereHas('project', function($q) {
                $q->where('status', 'in_progress'); 
            })->count();
            
        $activeProjectsList = Application::where('developer_id', $uid)
            ->where('status', 'accepted')
            ->whereHas('project', function($q) {
                $q->where('status', 'in_progress');
            })
            ->with(['project', 'project.company'])
            ->take(3)
            ->get()
            ->map(function($app) use ($uid) {
                return [
                    'id' => $app->project->id,
                    'title' => $app->project->title,
                    'client' => $app->project->company?->name ?? 'Confidencial',
                    'progress' => $app->project->getDeveloperProgress($uid),
                    'deadline' => $app->project->deadline ?? 'N/A',
                    'value' => '$' . number_format($app->project->budget_min ?? 0, 0),
                    'project' => (new \App\Http\Resources\ProjectResource($app->project))->resolve()
                ];
            });

        // 3. Rating Promedio
        $averageRating = $user->reviewsReceived()->avg('rating') ?? 0;
        $reviewsCount = $user->reviewsReceived()->count();

        // 4. Mensajes sin leer — includes group chats via conversation_participants pivot
        $unreadMessages = Message::where('sender_id', '!=', $uid)
            ->where('is_read', false)
            ->whereHas('conversation', function($q) use ($uid) {
                $q->where(function($sub) use ($uid) {
                    // Direct chats
                    $sub->where('initiator_id', $uid)
                        ->orWhere('participant_id', $uid);
                })->orWhereHas('participants', function($pivot) use ($uid) {
                    // Group chats via pivot table
                    $pivot->where('user_id', $uid);
                });
            })
            ->count();

        // 5. Recent Activity — real events from the system
        $recentActivity = [];
        
        // Recent applications
        $recentApps = Application::where('developer_id', $uid)
            ->latest()
            ->take(2)
            ->get();
            
        foreach($recentApps as $app) {
            $recentActivity[] = [
                'type' => 'application',
                'title' => 'Postulación a ' . ($app->project->title ?? 'Proyecto'),
                'description' => 'Estado: ' . $app->status,
                'time' => $app->created_at->diffForHumans(),
                'unread' => $app->status === 'accepted'
            ];
        }

        // Recent reviews received
        $recentReviews = $user->reviewsReceived()
            ->with('project')
            ->latest()
            ->take(2)
            ->get();

        foreach($recentReviews as $review) {
            $recentActivity[] = [
                'type' => 'project_completed',
                'title' => 'Review recibida: ' . ($review->project->title ?? 'Proyecto'),
                'description' => "Rating: {$review->rating}/5 — " . ($review->comment ? \Illuminate\Support\Str::limit($review->comment, 60) : 'Sin comentario'),
                'time' => $review->created_at->diffForHumans(),
                'unread' => true
            ];
        }

        // Recent wallet deposits (payments received)
        if ($user->wallet) {
            $recentPayments = $user->wallet->transactions()
                ->whereIn('type', $earningTypes)
                ->where('amount', '>', 0)
                ->latest()
                ->take(2)
                ->get();

            foreach($recentPayments as $payment) {
                $recentActivity[] = [
                    'type' => 'project_completed',
                    'title' => 'Pago recibido: $' . number_format($payment->amount, 2),
                    'description' => $payment->description ?? 'Pago por proyecto',
                    'time' => $payment->created_at->diffForHumans(),
                    'unread' => false
                ];
            }
        }

        // Sort activity by most recent
        usort($recentActivity, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        // Limit to 5 most recent
        $recentActivity = array_slice($recentActivity, 0, 5);

        return [
            'stats' => [
                'earnings_month' => round((float)$earningsThisMonth, 2),
                'earnings_growth' => round($earningsGrowth, 1),
                'active_projects' => $activeProjectsCount,
                'rating' => round($averageRating, 1),
                'reviews_count' => $reviewsCount,
                'unread_messages' => $unreadMessages,
            ],
            'active_projects' => $activeProjectsList,
            'recent_activity' => $recentActivity
        ];
    }
}
