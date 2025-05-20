<?php

use PHPUnit\Framework\TestCase;

class NasTasksTest extends TestCase
{
    private array $data;

    protected function setUp(): void
    {
        $this->data = [
            'nas1' => [
                'task_22c92d' => [
                    'task_name' => 'task_22c92d',
                    'task_id' => 71,
                    'schedule' => ['type' => 'Weekly', 'hour' => 20, 'minute' => 0, 'weekdays' => range(0, 6)],
                    'formatted_time' => '20:00'
                ],
                'task_afc323' => [
                    'task_name' => 'task_afc323',
                    'task_id' => 73,
                    'schedule' => ['type' => 'Weekly', 'hour' => 23, 'minute' => 30, 'weekdays' => range(0, 6)],
                    'formatted_time' => '23:30'
                ],
            ],
            'nas2' => [
                'task_22c92d' => [
                    'task_name' => 'task_22c92d',
                    'task_id' => 21,
                    'schedule' => ['type' => 'Weekly', 'hour' => 8, 'minute' => 0, 'weekdays' => range(0, 6)],
                    'formatted_time' => '08:00'
                ],
                'task_afc323' => [
                    'task_name' => 'task_afc323',
                    'task_id' => 22,
                    'schedule' => ['type' => 'Weekly', 'hour' => 11, 'minute' => 30, 'weekdays' => range(0, 6)],
                    'formatted_time' => '11:30'
                ],
            ]
        ];
    }

    public function testTasksExistOnBothNas(): void
    {
        foreach (array_keys($this->data['nas1']) as $taskKey) {
            $this->assertArrayHasKey($taskKey, $this->data['nas2']);
        }
    }

    public function testScheduleTypeIsWeekly(): void
    {
        foreach (['nas1', 'nas2'] as $nas) {
            foreach ($this->data[$nas] as $task) {
                $this->assertSame('Weekly', $task['schedule']['type']);
            }
        }
    }

    public function test12HourOffsetBetweenNasSchedules(): void
    {
        foreach (array_keys($this->data['nas1']) as $taskKey) {
            $hour1 = $this->data['nas1'][$taskKey]['schedule']['hour'];
            $hour2 = $this->data['nas2'][$taskKey]['schedule']['hour'];
            $this->assertTrue(abs($hour1 - $hour2) === 12 || abs($hour1 - $hour2) === 12 % 24, "Offset mismatch for $taskKey");
        }
    }

    public function testWeekdaysAreFullWeek(): void
    {
        foreach (['nas1', 'nas2'] as $nas) {
            foreach ($this->data[$nas] as $task) {
                $this->assertSame(range(0, 6), $task['schedule']['weekdays']);
            }
        }
    }
}
