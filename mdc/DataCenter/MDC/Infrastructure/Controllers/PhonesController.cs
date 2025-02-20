﻿using MDC.Common.Network.HttpWeb.Attributes;

using MDC.Data.Dtos;

using MDC.Infrastructure.Services;
using MDC.Infrastructure.Services.Interfaces;

using System.Threading.Tasks;

namespace MDC.Infrastructure.Controllers
{
    [WebRoute("phones")]
    public class PhonesController
    {
        private readonly IPhonesService phonesService;

        public PhonesController(PhonesService phonesService)
        {
            this.phonesService = phonesService;
        }

        public async Task<long?> GetNumberForUser(string userName)
        {
            return await phonesService.GetNumberForUser(userName);
        }

        public async Task<string> GetUserNameByNumber(long number)
        {
            return await phonesService.GetUserNameByNumber(number);
        }

        public async Task<long?> GetNumberForOrganization(string organizationName)
        {
            return await phonesService.GetNumberForOrganization(organizationName);
        }

        public async Task<long> CreateNumberForOrganization(string organizationName)
        {
            return await phonesService.CreateNumberForOrganization(organizationName);
        }

        public async Task<double> GetBalance(string userName)
        {
            return await phonesService.GetBalance(userName);
        }

        public async Task<bool> AddBalance(BalanceDto dto)
        {
            return await phonesService.AddBalance(dto.Name, dto.Amount);
        }

        public async Task<bool> ReduceBalance(BalanceDto dto)
        {
            return await phonesService.ReduceBalance(dto.Name, dto.Amount);
        }
    }
}
