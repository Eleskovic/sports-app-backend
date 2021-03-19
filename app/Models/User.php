<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return HasMany
     */
    public function meditations(): HasMany
    {
        return $this->hasMany(Meditation::class);
    }

    /**
     * Returns insights for a user
     */
    public function getInsights()
    {
        $yearStreaks     = [];
        $monthStreaks    = [];
        $yearBreakdowns  = [];
        $monthBreakdowns = [];
        $lastWeekBreakdowns = [];
        $output          = [];


        $monthBreakdown       = $this->breakdown('month');
        $yearBreakdown        = $this->breakdown('year');
        $lastWeekBreakdown    = $this->lastWeekBreakdown();
        $yearStreakBreakdown  = $this->maxStreak('year');
        $monthStreakBreakdown = $this->maxStreak('month');


        // Creating date-plucked arrays so front-end developers can access the specific date by key.
        foreach ($yearBreakdown as $key => $value) {
            $yearBreakdowns[$value->medDate] = $value; //
        }
        foreach ($monthBreakdown as $key => $value) {
            $monthBreakdowns[$value->medDate] = $value;
        }
        foreach ($yearStreakBreakdown as $key => $value) {
            $yearStreaks[$value->medDate] = $value;
        }
        foreach ($monthStreakBreakdown as $key => $value) {
            $monthStreaks[$value->medDate] = $value;
        }
        foreach ($lastWeekBreakdown as $key => $value) {
            $lastWeekBreakdowns[$value->medDate] = $value;
        }

        //Create output object
        foreach ($yearBreakdowns as $key => $value) {
            $output['breakdowns']['year'][$value->medDate] = array_merge($value->toArray(), [
                'max_streak' => $yearStreaks[$value->medDate]->total ?? 0
            ]);
        }

        foreach ($monthBreakdowns as $key => $value) {
            $output['breakdowns']['month'][$value->medDate] = array_merge($value->toArray(), [
                'max_streak' => $monthStreaks[$value->medDate]->total ?? 0
            ]);
        }

        $output['last_week'] = $lastWeekBreakdowns;
        $output['month_performance'] = $this->monthPerformance();
        return $output;
    }

    /**
     * Returns users longest streaks for the specified period
     * @param string $period
     * @return array
     */
    private function maxStreak(string $period)
    {
        if (!in_array($period, ['month', 'year'])) return [];

        $dateString = ($period == 'month') ? '%Y-%m' : '%Y';
        // Using this query as raw since there is no wrapper in Eloquent around MySQL WITH keyword.
        return DB::select("
                    WITH streaks as (
            SELECT MIN(started_at) start
                 , MAX(started_at) end
                 , COUNT(*) total
            FROM
                ( SELECT m.*
                       , CASE WHEN @prevx > started_at - INTERVAL 1 DAY THEN @ix:=@ix+1 ELSE @ix:=1 END i
                       , CASE WHEN @ix=1 THEN @jx:=@jx+1 ELSE @jx:=@jx END j
                       , @prevx := started_at
                  FROM meditations as m
                     , (SELECT @prevx:=null,@ix:=1,@jx:=0) vars
                  WHERE m.user_id = {$this->id}
                  ORDER
                      BY m.started_at
                ) x
            GROUP
                BY j)

            SELECT
                DATE_FORMAT(start, '{$dateString}') as medDate,
                   total
            FROM streaks
            GROUP BY medDate, total
            ORDER BY total DESC"
        );
    }

    /**
     * @param string $period
     * @return object
     */
    private function breakdown(string $period): object
    {
        if (!in_array($period, ['month', 'year'])) {
            throw new \LogicException('Unidentified period!');
        };
        $dateString = ($period == 'month') ? '%Y-%m' : '%Y';

        return Meditation::select([
            DB::raw('SUM(duration) as totalDuration'),
            DB::raw('count(*) as totalMeds'),
            DB::raw("DATE_FORMAT(started_at, '{$dateString}') as medDate")
        ])
            ->where('user_id', $this->id)
            ->groupBy('medDate')
            ->get();
    }

    /**
     * @return mixed
     */
    private function lastWeekBreakdown(): object
    {
        $today = Carbon::now();
        $lastWeek = Carbon::now()->subWeek();
        return Meditation::select([
            DB::raw('SUM(duration) as totalDuration'),
            DB::raw('DATE_FORMAT(started_at, \'%Y-%m-%d\') as medDate')
        ])
            ->where('user_id', $this->id)
            ->whereBetween('started_at', [$lastWeek, $today])
            ->groupBy('medDate')
            ->get();
    }

    private function monthPerformance() {
        return Meditation::select(DB::raw(" DISTINCT(DAY(started_at)) as day"))
            ->where('user_id', $this->id)
            ->whereRaw('MONTH(started_at) = MONTH(CURRENT_DATE())')
            ->whereRaw('YEAR(started_at) = YEAR(CURRENT_DATE())')
            ->orderBy('day', 'ASC')
            ->get()
            ->pluck('day');
    }
 }
