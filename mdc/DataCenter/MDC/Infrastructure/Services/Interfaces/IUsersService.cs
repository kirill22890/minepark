﻿using MDC.Data.Dtos;
using MDC.Data.Models;
using System.Threading.Tasks;

namespace MDC.Infrastructure.Services.Interfaces
{
    public interface IUsersService
    {
        Task<bool> Exist(string userName);

        Task<User> GetUser(string userName);

        Task<User> GetUser(int userId);

        Task<UserDto> GetUserDto(string userName);

        Task<UserSettingsDto> GetUserSettingsDto(string unitId, string userName);

        Task<string> GetPassword(string userName);

        Task<bool> ExistPassword(string userName);

        Task SetPassword(string userName, string password);

        Task ResetPassword(string userName);

        Task<UserDto> CreateInternal(string unitId, string userName);

        Task Update(UserDto userDto);

        Task UpdateSettings(string unitId, UserSettingsDto settingsDto);

        Task UpdateJoinStatus(string unitId, string userName);

        Task UpdateQuitStatus(string unitId, string userName);

        Task SaveExecutedCommandAuditRecord(string unitId, string userName, string command);

        Task SaveChatMessageAuditRecord(string unitId, string userName, string message);
    }
}
