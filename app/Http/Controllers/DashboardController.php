<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Project, Application};
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
        $budget = Project::where('company_id',$uid)->sum(DB::raw('COALESCE(budget_max, budget_min)'));
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

        // 1. Ganado este mes (Earnings this month)
        // Assuming wallet transactions store earnings properly with type 'deposit'
        // If not using transactions yet, we might use wallet balance as total.
        // For "this month", we look at transactions.
        $earningsThisMonth = $user->wallet 
            ? $user->wallet->transactions()
                ->where('type', 'deposit')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount')
            : 0;

        $previousMonthEarnings = $user->wallet
            ? $user->wallet->transactions()
                ->where('type', 'deposit')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount')
            : 0;
            
        $earningsGrowth = $previousMonthEarnings > 0 
            ? (($earningsThisMonth - $previousMonthEarnings) / $previousMonthEarnings) * 100 
            : 0;

        // 2. Proyectos Activos (Active Projects)
        // Based on applications accepted and project status
        $activeProjectsCount = Application::where('developer_id', $uid)
            ->where('status', 'accepted')
            ->whereHas('project', function($q) {
                $q->whereIn('status', ['in_progress', 'open']); 
            })->count();
            
        $activeProjectsList = Application::where('developer_id', $uid)
            ->where('status', 'accepted')
            ->whereHas('project', function($q) {
                $q->whereIn('status', ['in_progress', 'open']);
            })
            ->with(['project', 'project.company'])
            ->take(3)
            ->get()
            ->map(function($app) {
                return [
                    'id' => $app->project->id,
                    'title' => $app->project->title,
                    'client' => $app->project->company?->name ?? 'Confidencial',
                    'progress' => 0, // No progress logic yet?
                    'deadline' => $app->project->deadline ?? 'N/A',
                    'value' => '€' . number_format($app->project->budget_min ?? 0, 0)
                ];
            });

        // 3. Rating Promedio
        $averageRating = $user->reviewsReceived()->avg('rating') ?? 0;
        $reviewsCount = $user->reviewsReceived()->count();

        // 4. Mensajes sin leer
        // Message model has 'is_read' boolean
        // 4. Mensajes sin leer
        // Messages are linked to conversations. We need to count messages in conversations where:
        // - Auth user is a participant (initiator or participant)
        // - Message sender is NOT auth user
        // - Message is not read
        $unreadMessages = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where(function($query) use ($uid) {
                $query->where('conversations.initiator_id', $uid)
                      ->orWhere('conversations.participant_id', $uid);
            })
            ->where('messages.sender_id', '!=', $uid)
            ->where('messages.is_read', false)
            ->count();

        // 5. Recent Activity (Mocked for now mostly, or fetch real events)
        $recentActivity = [];
        
        // Add recent applications
        $recentApps = Application::where('developer_id', $uid)
            ->latest()
            ->take(2)
            ->get();
            
        foreach($recentApps as $app) {
            $recentActivity[] = [
                'type' => 'application',
                'title' => 'Postulación a ' . $app->project->title,
                'description' => 'Estado: ' . $app->status,
                'time' => $app->created_at->diffForHumans(),
                'unread' => false
            ];
        }

        return [
            'stats' => [
                'earnings_month' => $earningsThisMonth,
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
