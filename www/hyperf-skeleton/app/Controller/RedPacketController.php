<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RedPacketService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class RedPacketController extends AbstractController
{
    /**
     * @var ValidatorFactoryInterface
     */
    #[Inject]
    protected $validationFactory;

    /**
     * @var RedPacketService
     */
    #[Inject]
    protected $redPacketService;

    /**
     * 创建红包
     */
    public function create()
    {
        try {
            // 验证请求参数
            $validated = $this->validate([
                'user_id'  => 'required|integer|exists:users,id',
                'amount'   => 'required|numeric|min:0.01',
                'num'      => 'required|integer|min:1',
                'type'     => 'required|integer|in:1,2',
                'blessing' => 'nullable|string|max:255',
            ]);
        } catch (\Throwable $e) {
            return $this->response->json([
                'code'    => 422,
                'message' => $e->getMessage(),
                'data'    => null,
            ])->withStatus(422);
        }

        $userId   = $validated['user_id'];
        $amount   = $validated['amount'];
        $num      = $validated['num'];
        $type     = $validated['type'];
        $blessing = $validated['blessing'] ?? '恭喜发财，大吉大利！';

        // 调用Service层创建红包
        $result = $this->redPacketService->createRedPacket((int) $userId, (float) $amount, (int) $num, (int) $type, $blessing);

        return $this->response->json($result);
    }

    /**
     * 抢红包
     */
    public function grab()
    {
        // 验证请求参数
        $validated = $this->validate([
            'user_id'   => 'required|integer',
            'packet_no' => 'required|string',
        ]);

        $userId   = $validated['user_id'];
        $packetNo = $validated['packet_no'];

        // 调用Service层抢红包
        $result = $this->redPacketService->grabRedPacket((int) $userId, $packetNo);

        return $this->response->json($result);
    }


    /**
     * 红包详情
     */
    public function detail()
    {
        // 验证请求参数
        $validated = $this->validate([
            'packet_no' => 'required|string',
        ]);

        $packetNo = $validated['packet_no'];

        // 调用Service层获取红包详情
        $result = $this->redPacketService->getRedPacketDetail($packetNo);

        return $this->response->json($result);
    }


    /**
     * 验证请求数据
     *
     * @param array $rules 验证规则
     * @param array $messages 错误信息
     * @return array 验证通过的数据
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $validator = $this->validationFactory->make(
            $this->request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        return $validator->validated();
    }
}