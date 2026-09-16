<?php

namespace Database\Seeders;

use App\Enums\IdStatus;
use App\Models\IdRecord;
use App\Models\IdStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class IdRecordSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();

        $samples = [
            [
                'name'              => 'Ciara Maricar M. Tan',
                'position'          => 'Admin Assistant',
                'id_number'         => '200473',
                'date_hired'        => '2018-02-28',
                'birth_date'        => '1992-11-03',
                'emergency_contact' => 'Carlos Tan: 09174468097',
                'image_path'        => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_FRONT\Employee ID Images\Calamba\Ciara Maricar Tan.png',
                'signature_path'    => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_BACK\Employee Signature\Calamba\Ciara Maricar Tan_Signature.png',
                'status'            => IdStatus::FOR_PROCESSING->value,
            ],
            [
                'name'              => 'Elvin N. Opano',
                'position'          => 'Admin Officer',
                'id_number'         => '200291',
                'date_hired'        => '2010-02-01',
                'birth_date'        => '1986-05-26',
                'emergency_contact' => 'Rosalinda Opano: 09123915364',
                'image_path'        => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_FRONT\Employee ID Images\Calamba\Elvin Opano.png',
                'signature_path'    => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_BACK\Employee Signature\Calamba\Elvin Opano_Signature.png',
                'status'            => IdStatus::READY->value,
            ],
            [
                'name'              => 'Robert John L. Holgado',
                'position'          => 'Billing and Collection Supervisor',
                'id_number'         => '200407',
                'date_hired'        => '2015-04-20',
                'birth_date'        => '1986-04-09',
                'emergency_contact' => 'Sheryl Holgado: 09485619343',
                'image_path'        => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_FRONT\Employee ID Images\Calamba\Billie Jo Riego.png',
                'signature_path'    => 'Z:\IT_Files\IT_Shared_Files\Smart iDesigner\IMS_ID_BACK\Employee Signature\Calamba\Robert John Holgado_Signature.png',
                'status'            => IdStatus::RELEASED->value,
            ],
            [
                'name'              => 'Maria Santos',
                'position'          => 'HR Officer',
                'id_number'         => '200501',
                'date_hired'        => '2019-06-15',
                'birth_date'        => '1990-03-22',
                'emergency_contact' => 'Jose Santos: 09181234567',
                'image_path'        => null,
                'signature_path'    => null,
                'status'            => IdStatus::PENDING->value,
            ],
            [
                'name'              => 'Ana Reyes',
                'position'          => 'Finance Staff',
                'id_number'         => '200512',
                'date_hired'        => '2020-01-10',
                'birth_date'        => '1993-07-14',
                'emergency_contact' => 'Ben Reyes: 09209876543',
                'image_path'        => null,
                'signature_path'    => null,
                'status'            => IdStatus::LOST->value,
            ],
            [
                'name'              => 'Carlos Dela Cruz',
                'position'          => 'IT Specialist',
                'id_number'         => '200388',
                'date_hired'        => '2013-11-05',
                'birth_date'        => '1985-09-30',
                'emergency_contact' => 'Luz Dela Cruz: 09175551234',
                'image_path'        => null,
                'signature_path'    => null,
                'status'            => IdStatus::DAMAGED->value,
            ],
            [
                'name'              => 'Jenny Bautista',
                'position'          => 'Secretary',
                'id_number'         => '200620',
                'date_hired'        => '2022-03-01',
                'birth_date'        => '1998-12-05',
                'emergency_contact' => null,
                'image_path'        => null,
                'signature_path'    => null,
                'status'            => IdStatus::CANCELLED->value,
            ],
        ];

        foreach ($samples as $data) {
            $record = IdRecord::firstOrCreate(
                ['id_number' => $data['id_number']],
                $data
            );

            // Add seed status history entry if admin exists and record is fresh
            if ($admin && $record->wasRecentlyCreated) {
                IdStatusHistory::create([
                    'id_record_id' => $record->id,
                    'old_status'   => IdStatus::PENDING->value,
                    'new_status'   => $data['status'],
                    'changed_by'   => $admin->id,
                    'remarks'      => '[SEED DATA] Initial status set.',
                ]);
            }
        }
    }
}
