<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    public function run()
    {
        Group::create(['name' => 'Admin']);
        Group::create(['name' => 'Uploader']);
        Group::create(['name' => 'Viewer']);
    }
}
